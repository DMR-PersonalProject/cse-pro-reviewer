<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Audit\Audit;
use App\Domain\Content\AttachMedia;
use App\Enums\ImportFlag;
use App\Enums\ImportRowDecision;
use App\Enums\QuestionStatus;
use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\Tag;
use App\Models\Topic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Turns pending rows without errors into items, all in one transaction (FR-M2, FR-M6). Items arrive
 * as draft, or needs_review with the reasons of their flags; never published. A missing answer
 * stays missing: no choice is marked correct.
 */
class ApproveImportRows
{
    private const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /** @var list<string> files copied to the public disk during this call */
    private array $storedFiles = [];

    public function __construct(
        private readonly AttachMedia $attachMedia,
        private readonly StagedMedia $stagedMedia,
        private readonly RecountImportBatch $recount,
    ) {}

    /**
     * @param  list<int>  $rowIds  rows of this batch; others are ignored
     */
    public function handle(ImportBatch $batch, array $rowIds): ApprovalResult
    {
        $this->storedFiles = [];

        try {
            return DB::transaction(fn () => $this->approve($batch, $rowIds));
        } catch (Throwable $e) {
            Storage::disk(AttachMedia::DISK)->delete($this->storedFiles);

            throw $e;
        } finally {
            $batch->refresh();
        }
    }

    /**
     * @param  list<int>  $rowIds
     */
    private function approve(ImportBatch $batch, array $rowIds): ApprovalResult
    {
        $rows = $batch->rows()->whereKey($rowIds)->lockForUpdate()->get();
        $approvable = $rows->filter(fn (ImportRow $row) => $row->decision === ImportRowDecision::Pending && ! $row->hasErrors());

        if ($approvable->isNotEmpty()) {
            $source = $this->source($batch);
            $passageIds = $batch->meta['passage_ids'] ?? [];

            foreach ($approvable as $row) {
                $ref = $row->payload['passage_ref'];

                if ($ref !== null && ! isset($passageIds[$ref])) {
                    $passageIds[$ref] = $this->createPassage($batch, $batch->meta['passages'][$ref], $source)->id;
                }

                $question = $this->createQuestion($batch, $row, $source, $ref === null ? null : $passageIds[$ref]);
                $row->forceFill(['decision' => ImportRowDecision::Approved, 'question_id' => $question->id])->save();
            }

            $batch->forceFill(['meta' => [...$batch->meta, 'passage_ids' => $passageIds]])->save();
            $this->recount->handle($batch);

            Audit::record('import.rows_approved', $batch, meta: ['rows' => $approvable->pluck('row_number')->values()->all()]);
        }

        return new ApprovalResult($approvable->count(), count($rowIds) - $approvable->count());
    }

    /**
     * The batch's source: the one named in the file (reused unchanged if it exists), or one named
     * after the batch. Created sources are always private, whatever the file asked for (FR-M6).
     */
    private function source(ImportBatch $batch): Source
    {
        if ($batch->default_source_id && $existing = Source::find($batch->default_source_id)) {
            return $existing;
        }

        $declared = $batch->meta['default_source'] ?? null;
        $name = $declared['name'] ?? __('Import: :name', ['name' => $batch->name]);

        $source = Source::where('name', $name)->first() ?? Source::create([
            'name' => $name,
            'type' => SourceType::tryFrom((string) ($declared['type'] ?? '')) ?? SourceType::Other,
            'visibility' => SourceVisibility::Private,
            'created_by' => Auth::id(),
        ]);

        $batch->forceFill(['default_source_id' => $source->id])->save();

        return $source;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPassage(ImportBatch $batch, array $data, Source $source): Passage
    {
        [$subtestId, $topicId] = $this->taxonomyIds($data['subtest'], $data['topic']);

        $passage = Passage::create([
            'subtest_id' => $subtestId,
            'topic_id' => $topicId,
            'source_id' => $source->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'language' => $data['language'],
            'status' => QuestionStatus::Draft,
        ]);

        $this->attachStagedMedia($passage, $data['media'], $batch->meta['media_root'] ?? null);

        return $passage;
    }

    private function createQuestion(ImportBatch $batch, ImportRow $row, Source $source, ?int $passageId): Question
    {
        $data = $row->payload;
        [$subtestId, $topicId] = $this->taxonomyIds($data['subtest'], $data['topic']);
        $reasons = collect($row->flags)
            ->map(fn (array $flag) => ImportFlag::from($flag['code'])->reason())
            ->unique()
            ->values();

        $question = Question::create([
            'subtest_id' => $subtestId,
            'topic_id' => $topicId,
            'passage_id' => $passageId,
            'source_id' => $source->id,
            'import_batch_id' => $batch->id,
            'stem' => $data['stem'],
            'explanation' => $data['explanation'],
            'difficulty' => $data['difficulty'],
            'language' => $data['language'],
            'status' => $reasons->isEmpty() ? QuestionStatus::Draft : QuestionStatus::NeedsReview,
            'needs_review_reasons' => $reasons->all(),
            'lock_choice_order' => $data['lock_choice_order'],
            'extraction_confidence' => $data['extraction']['confidence'],
            'source_file' => $data['provenance']['file'],
            'source_page' => $data['provenance']['page'],
            'source_notes' => $this->notes($row),
        ]);

        foreach ($data['choices'] as $index => $body) {
            $question->choices()->create([
                'position' => $index + 1,
                'body' => $body,
                'is_correct' => $data['answer'] === self::LETTERS[$index],
            ]);
        }

        $question->tags()->sync(collect($data['tags'])->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id));
        $this->attachStagedMedia($question, $data['media'], $batch->meta['media_root'] ?? null);

        return $question;
    }

    /**
     * Attaches staged images that exist and have alt text; the rest are flagged on the row and
     * named in the item's notes. Alt text is never made up.
     *
     * @param  list<array{path: string, alt: ?string}>  $media
     */
    private function attachStagedMedia(Question|Passage $mediable, array $media, ?string $mediaRoot): void
    {
        foreach ($media as $item) {
            $file = $this->stagedMedia->resolve($mediaRoot, $item['path']);

            if ($file === null || $item['alt'] === null) {
                continue;
            }

            $this->storedFiles[] = $this->attachMedia->handle($mediable, $file, $item['alt'])->path;
        }
    }

    /**
     * Provenance and extraction notes, plus every flag's detail, so the reviewer knows what to fix.
     */
    private function notes(ImportRow $row): ?string
    {
        $lines = array_filter([
            $row->payload['provenance']['notes'],
            $row->payload['extraction']['notes'] === null
                ? null
                : __('Extraction: :notes', ['notes' => $row->payload['extraction']['notes']]),
        ]);

        foreach ($row->flags as $flag) {
            if ($flag['detail'] !== null && $flag['code'] !== ImportFlag::ExtractorFlagged->value) {
                $lines[] = ImportFlag::from($flag['code'])->label().': '.$flag['detail'];
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @return array{int, int}
     */
    private function taxonomyIds(string $subtestCode, string $topicCode): array
    {
        $subtestId = Subtest::where('code', $subtestCode)->value('id');

        return [$subtestId, Topic::where('subtest_id', $subtestId)->where('code', $topicCode)->value('id')];
    }
}
