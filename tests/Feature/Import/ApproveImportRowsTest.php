<?php

use App\Domain\Import\ApproveImportRows;
use App\Domain\Import\CreateImportBatch;
use App\Domain\Import\ImportParser;
use App\Domain\Import\RejectImportRows;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportFormat;
use App\Enums\ImportRowDecision;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\AuditLog;
use App\Models\Choice;
use App\Models\ImportBatch;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function approvalItem(array $overrides = []): array
{
    // Random sentences, so items in one batch are never possible duplicates of each other.
    return [
        'subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => fake()->unique()->sentence(12),
        'choices' => ['1', '2', '3'], 'answer' => 'B', 'explanation' => 'Add them.', ...$overrides,
    ];
}

function approvalBatch(array $items, array $data = [], ?string $mediaRoot = null): ImportBatch
{
    $json = json_encode(['schema_version' => 1, 'items' => $items, ...$data]);
    $import = app(ImportParser::class)->parse($json, ImportFormat::Json, 'staged.json');

    return app(CreateImportBatch::class)->handle($import, auth()->user(), $mediaRoot);
}

function approveAll(ImportBatch $batch)
{
    return app(ApproveImportRows::class)->handle($batch, $batch->rows()->pluck('id')->all());
}

it('creates a draft item with choices, tags, provenance and the batch link', function () {
    $batch = approvalBatch([approvalItem([
        'difficulty' => 'easy', 'tags' => ['addition', 'warm-up'], 'lock_choice_order' => true,
        'provenance' => ['file' => 'reviewer-x.pdf', 'page' => 12, 'notes' => 'Top of page'],
        'extraction' => ['confidence' => 0.92],
    ])]);

    $result = approveAll($batch);

    $row = $batch->rows()->first();
    $question = $row->question;

    expect($result->approved)->toBe(1)
        ->and($row->decision)->toBe(ImportRowDecision::Approved)
        ->and($question->status)->toBe(QuestionStatus::Draft)
        ->and($question->needs_review_reasons)->toBeEmpty()
        ->and($question->importBatch->is($batch))->toBeTrue()
        ->and($question->choices->pluck('body')->all())->toBe(['1', '2', '3'])
        ->and($question->correctChoice()->body)->toBe('2')
        ->and($question->lock_choice_order)->toBeTrue()
        ->and($question->difficulty->value)->toBe('easy')
        ->and($question->tags->pluck('name')->sort()->values()->all())->toBe(['addition', 'warm-up'])
        ->and($question->source_file)->toBe('reviewer-x.pdf')
        ->and($question->source_page)->toBe('12')
        ->and($question->source_notes)->toContain('Top of page')
        ->and((float) $question->extraction_confidence)->toBe(0.92);
});

it('never publishes, and a missing key gives needs_review with no correct choice (AC-20)', function () {
    $batch = approvalBatch([approvalItem(), approvalItem(), approvalItem(), approvalItem(['answer' => null])]);

    approveAll($batch);

    $fourth = $batch->rows()->where('row_number', 4)->first()->question;
    expect($fourth->status)->toBe(QuestionStatus::NeedsReview)
        ->and($fourth->needs_review_reasons->all())->toBe([NeedsReviewReason::MissingAnswerKey])
        ->and($fourth->choices->where('is_correct', true))->toBeEmpty()
        ->and(Question::where('status', QuestionStatus::Published)->count())->toBe(0);
});

it('stores one reason per flag type', function () {
    $batch = approvalBatch([approvalItem([
        'explanation' => null, 'extraction' => ['confidence' => 0.5, 'needs_review' => true, 'notes' => 'Blurry'],
    ])]);

    approveAll($batch);

    expect($batch->rows()->first()->question->needs_review_reasons->all())->toBe([
        NeedsReviewReason::MissingExplanation, NeedsReviewReason::LowConfidence, NeedsReviewReason::FlaggedInSource,
    ]);
});

it('puts items under a new private source when none is given (AC-23)', function () {
    $batch = approvalBatch([approvalItem()]);

    approveAll($batch);

    $source = $batch->rows()->first()->question->source;
    expect($source->visibility)->toBe(SourceVisibility::Private)
        ->and($source->name)->toBe(__('Import: :name', ['name' => 'staged']))
        ->and($batch->refresh()->default_source_id)->toBe($source->id);
});

it('creates the file’s source as private even when the file asks for public', function () {
    $batch = approvalBatch([approvalItem()], ['batch' => ['default_source' => ['name' => 'Reviewer X', 'type' => 'reviewer', 'visibility' => 'public']]]);

    approveAll($batch);

    $source = Source::where('name', 'Reviewer X')->sole();
    expect($source->visibility)->toBe(SourceVisibility::Private)
        ->and($source->type)->toBe(SourceType::Reviewer)
        ->and($batch->meta['warnings'])->toHaveCount(1);
});

it('reuses an existing source without changing its visibility', function (SourceVisibility $existing, string $asked) {
    $source = Source::factory()->create(['name' => 'Reviewer X', 'visibility' => $existing, 'type' => SourceType::Book]);
    $updatedAt = $source->updated_at;
    $this->travel(1)->minutes();

    $batch = approvalBatch([approvalItem()], ['batch' => ['default_source' => ['name' => 'Reviewer X', 'type' => 'reviewer', 'visibility' => $asked]]]);
    approveAll($batch);

    $source->refresh();
    expect(Source::count())->toBe(1)
        ->and($batch->rows()->first()->question->source_id)->toBe($source->id)
        ->and($source->visibility)->toBe($existing)
        ->and($source->type)->toBe(SourceType::Book)
        ->and($source->updated_at->equalTo($updatedAt))->toBeTrue();
})->with([
    'public kept public, file asks private' => [SourceVisibility::Public, 'private'],
    'private kept private, file asks public' => [SourceVisibility::Private, 'public'],
]);

it('creates each passage once and keeps item order', function () {
    $passage = ['ref' => 'p1', 'subtest' => 'verbal', 'topic' => 'reading_comprehension', 'title' => 'Corner', 'body' => 'Text'];
    $inPassage = fn (string $stem) => approvalItem(['subtest' => 'verbal', 'topic' => 'reading_comprehension', 'passage_ref' => 'p1', 'stem' => $stem]);
    $batch = approvalBatch([$inPassage('First question about the corner?'), approvalItem(), $inPassage('Second question on the reading corner text?')], ['passages' => [$passage]]);

    app(ApproveImportRows::class)->handle($batch, [$batch->rows[0]->id]);
    app(ApproveImportRows::class)->handle($batch, [$batch->rows[2]->id]);

    expect(Passage::count())->toBe(1);
    $created = Passage::first();
    expect($created->title)->toBe('Corner')
        ->and($created->status)->toBe(QuestionStatus::Draft)
        ->and($created->questions()->orderBy('passage_position')->pluck('stem')->all())
        ->toBe(['First question about the corner?', 'Second question on the reading corner text?']);
});

it('does not approve rows with errors', function () {
    $batch = approvalBatch([approvalItem(), approvalItem(['choices' => ['only one']])]);

    $result = approveAll($batch);

    expect($result->approved)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($batch->rows[1]->refresh()->decision)->toBe(ImportRowDecision::Pending)
        ->and($batch->rows[1]->question_id)->toBeNull()
        ->and(Question::count())->toBe(1);
});

it('does not approve a row twice or a rejected row', function () {
    $batch = approvalBatch([approvalItem(), approvalItem()]);
    app(RejectImportRows::class)->handle($batch, [$batch->rows[1]->id]);

    approveAll($batch);
    $result = approveAll($batch);

    expect($result->approved)->toBe(0)
        ->and(Question::count())->toBe(1)
        ->and($batch->rows[1]->refresh()->decision)->toBe(ImportRowDecision::Rejected);
});

it('updates the batch counts, status and audit log', function () {
    $batch = approvalBatch([approvalItem(), approvalItem(), approvalItem()]);

    app(ApproveImportRows::class)->handle($batch, [$batch->rows[0]->id]);
    expect($batch->refresh()->approved_rows)->toBe(1)
        ->and($batch->status)->toBe(ImportBatchStatus::Pending);

    app(RejectImportRows::class)->handle($batch, [$batch->rows[1]->id, $batch->rows[2]->id]);
    expect($batch->refresh()->rejected_rows)->toBe(2)
        ->and($batch->status)->toBe(ImportBatchStatus::Completed)
        ->and(AuditLog::where('action', 'import.rows_approved')->sole()->meta)->toBe(['rows' => [1]])
        ->and(AuditLog::where('action', 'import.rows_rejected')->sole()->meta)->toBe(['rows' => [2, 3]]);
});

it('ignores rows from another batch', function () {
    $batch = approvalBatch([approvalItem()]);
    $other = approvalBatch([approvalItem()]);

    $result = app(ApproveImportRows::class)->handle($batch, [$other->rows[0]->id]);

    expect($result->approved)->toBe(0)
        ->and(Question::count())->toBe(0);
});

it('rolls back the whole call on a failure', function () {
    $batch = approvalBatch([approvalItem(['tags' => ['t1']]), approvalItem(['stem' => 'This one fails on purpose, sadly.'])], [
        'passages' => [],
    ]);
    Question::creating(function (Question $question) {
        if (str_contains($question->stem, 'fails on purpose')) {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => approveAll($batch))->toThrow(RuntimeException::class, 'boom');

    expect(Question::count())->toBe(0)
        ->and(Choice::count())->toBe(0)
        ->and(Tag::count())->toBe(0)
        ->and(Source::count())->toBe(0)
        ->and($batch->rows()->where('decision', ImportRowDecision::Approved)->count())->toBe(0)
        ->and($batch->refresh()->approved_rows)->toBe(0);
});

describe('media', function () {
    beforeEach(function () {
        $this->staged = sys_get_temp_dir().'/cse-approve-'.uniqid();
        mkdir($this->staged.'/media', recursive: true);
        imagepng(imagecreatetruecolor(40, 30), $this->staged.'/media/chart.png');
    });

    afterEach(function () {
        unlink($this->staged.'/media/chart.png');
        rmdir($this->staged.'/media');
        rmdir($this->staged);
    });

    it('copies and attaches a staged image with alt text', function () {
        $batch = approvalBatch([approvalItem(['media' => [['path' => 'media/chart.png', 'alt' => 'Bar chart of sales']]])], mediaRoot: $this->staged);

        approveAll($batch);

        $media = $batch->rows()->first()->question->media->sole();
        expect($media->alt_text)->toBe('Bar chart of sales')
            ->and($media->mime)->toBe('image/png')
            ->and(Storage::disk('public')->exists($media->path))->toBeTrue()
            ->and($batch->rows()->first()->question->status)->toBe(QuestionStatus::Draft);
    });

    it('does not attach an image without alt text and names it for the editor', function () {
        $batch = approvalBatch([approvalItem([
            'media' => [['path' => 'media/chart.png', 'alt' => null]],
            'provenance' => ['file' => 'reviewer-x.pdf', 'page' => 7],
        ])], mediaRoot: $this->staged);

        expect(array_column($batch->rows[0]->flags, 'code'))->toBe(['missing_alt_text']);

        approveAll($batch);

        $question = $batch->rows()->first()->question;
        expect($question->status)->toBe(QuestionStatus::NeedsReview)
            ->and($question->needs_review_reasons->all())->toBe([NeedsReviewReason::MissingMedia])
            ->and($question->media)->toBeEmpty()
            ->and(Media::count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([])
            ->and($question->source_notes)->toContain('media/chart.png')->toContain('reviewer-x.pdf')->toContain('7')
            ->and($question->source_notes)->toContain(__('has no alt text'));
    });

    it('attaches passage media to the passage', function () {
        $batch = approvalBatch([approvalItem(['subtest' => 'analytical', 'topic' => 'data_interpretation', 'passage_ref' => 't1'])], [
            'passages' => [['ref' => 't1', 'subtest' => 'analytical', 'topic' => 'data_interpretation', 'body' => null,
                'media' => [['path' => 'media/chart.png', 'alt' => 'Table of rainfall']]]],
        ], $this->staged);

        approveAll($batch);

        expect(Passage::sole()->media->sole()->alt_text)->toBe('Table of rainfall');
    });

    it('removes copied files when the call rolls back', function () {
        $batch = approvalBatch([
            approvalItem(['media' => [['path' => 'media/chart.png', 'alt' => 'Chart']]]),
            approvalItem(['stem' => 'This one fails on purpose, sadly.']),
        ], mediaRoot: $this->staged);
        Question::creating(function (Question $question) {
            if (str_contains($question->stem, 'fails on purpose')) {
                throw new RuntimeException('boom');
            }
        });

        expect(fn () => approveAll($batch))->toThrow(RuntimeException::class);

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(Media::count())->toBe(0);
    });
});
