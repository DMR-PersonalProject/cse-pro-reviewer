<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Models\AttemptAnswer;
use App\Models\Choice;
use App\Models\Media;
use App\Models\Question;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hard delete for a takedown (FR-Q5): the admin must type the item ID. Choices, tag links and
 * media rows go with it. Snapshots in past attempts keep the content unless `purge` is set
 * (FR-Q5a): then their content becomes a placeholder, while keys, correctness and scores stay.
 * Image files are deleted once nothing needs them: no bank row, and no kept snapshot.
 */
class DeleteQuestion
{
    /** Stored in place of purged text; the review shows its own translated notice (FR-S6). */
    public const PLACEHOLDER = 'Content removed (takedown)';

    /**
     * @throws DomainException when the confirmation doesn't match the ID
     */
    public function handle(Question $question, string $confirmation, bool $purge = false): void
    {
        if (trim($confirmation) !== (string) $question->id) {
            throw new DomainException(__('Type the item ID to confirm.'));
        }

        $files = DB::transaction(function () use ($question, $purge) {
            $answers = $this->answers([$question->id]);
            $files = $this->remove($question, $answers, $purge);

            Audit::record('question.deleted', $question, meta: [
                'stem' => Str::limit($question->stem, 200),
                'source_id' => $question->source_id,
                'status' => $question->status->value,
                'purged' => $purge,
                'purged_answers' => $purge ? $answers->count() : 0,
            ]);

            return $files;
        });

        $this->deleteFiles($files);
    }

    /**
     * The attempt answers that snapshot these questions. Read them before the delete nulls `question_id`.
     *
     * @param  list<int>  $questionIds
     * @return Collection<int, AttemptAnswer>
     */
    public function answers(array $questionIds): Collection
    {
        return AttemptAnswer::query()->whereIn('question_id', $questionIds)->get(['id', 'question_id', 'snapshot']);
    }

    /**
     * Deletes the item and everything that hangs off it, and purges its snapshots when asked.
     * No audit (callers audit) and no file deletes, which can't roll back: it returns the files
     * that may go, for `deleteFiles()` after the transaction.
     *
     * @param  Collection<int, AttemptAnswer>  $answers  from `answers()`
     * @return list<array{disk: string, path: string}>
     */
    public function remove(Question $question, Collection $answers, bool $purge): array
    {
        $answers = $answers->where('question_id', $question->id);
        $choiceIds = $question->choices()->pluck('id');

        $media = Media::query()
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('mediable_type', $question->getMorphClass())->where('mediable_id', $question->id))
                ->orWhere(fn ($q) => $q->where('mediable_type', (new Choice)->getMorphClass())->whereIn('mediable_id', $choiceIds)));

        $owned = $media->get(['disk', 'path'])->map(fn (Media $m) => ['disk' => $m->disk, 'path' => $m->path])->all();
        $files = $this->filesOf($answers, $owned, $purge);

        if ($purge) {
            foreach ($answers as $answer) {
                $answer->update(['snapshot' => $this->placeholder($answer->snapshot), 'purged_at' => now()]);
            }
        }

        $media->delete();
        $question->delete();

        return $files;
    }

    /**
     * The files that may be deleted: without purge, the owned files no kept snapshot shows;
     * with purge, also every file the purged snapshots showed.
     *
     * @param  Collection<int, AttemptAnswer>  $answers
     * @param  list<array{disk: string, path: string}>  $owned
     * @return list<array{disk: string, path: string}>
     */
    public function filesOf(Collection $answers, array $owned, bool $purge): array
    {
        $shown = $answers->flatMap(fn (AttemptAnswer $answer) => $this->snapshotFiles($answer->snapshot))->all();

        if ($purge) {
            return [...$owned, ...$shown];
        }

        $kept = array_map(fn (array $file) => $file['disk'].'|'.$file['path'], $shown);

        return array_values(array_filter($owned, fn (array $file) => ! in_array($file['disk'].'|'.$file['path'], $kept, true)));
    }

    /**
     * Deletes each file unless a media row in the bank still points at it (a passage that stays).
     *
     * @param  list<array{disk: string, path: string}>  $files
     */
    public function deleteFiles(array $files): void
    {
        foreach (collect($files)->unique(fn (array $file) => $file['disk'].'|'.$file['path']) as $file) {
            if (! Media::query()->where('disk', $file['disk'])->where('path', $file['path'])->exists()) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{disk: string, path: string}>
     */
    private function snapshotFiles(array $snapshot): array
    {
        $media = [
            ...$snapshot['media'] ?? [],
            ...$snapshot['passage']['media'] ?? [],
            ...collect($snapshot['choices'] ?? [])->flatMap(fn (array $choice) => $choice['media'] ?? [])->all(),
        ];

        return array_map(fn (array $item) => ['disk' => $item['disk'], 'path' => $item['path']], $media);
    }

    /**
     * Keeps the choice keys (the stored answer and key still refer to them) and the metadata.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function placeholder(array $snapshot): array
    {
        return [
            ...$snapshot,
            'stem' => self::PLACEHOLDER,
            'media' => [],
            'passage' => $snapshot['passage'] === null ? null : ['body' => self::PLACEHOLDER, 'media' => []],
            'choices' => array_map(fn (array $choice) => ['key' => $choice['key'], 'body' => self::PLACEHOLDER, 'media' => []], $snapshot['choices']),
            'explanation' => self::PLACEHOLDER,
        ];
    }
}
