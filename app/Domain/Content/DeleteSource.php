<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Source;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a source together with its items and passages (FR-Q5): the admin must type the
 * source name exactly. Refused if one of its passages also holds another source's items.
 * `purge` replaces the content of every snapshot of its items (FR-Q5a).
 */
class DeleteSource
{
    public function __construct(private readonly DeleteQuestion $deleteQuestion) {}

    /**
     * @throws DomainException
     */
    public function handle(Source $source, string $confirmation, bool $purge = false): void
    {
        if ($confirmation !== $source->name) {
            throw new DomainException(__('Type the source name exactly to confirm.'));
        }

        $sharedPassages = Passage::query()
            ->where('source_id', $source->id)
            ->whereHas('questions', fn ($query) => $query->where('source_id', '!=', $source->id))
            ->count();

        if ($sharedPassages > 0) {
            throw new DomainException(__('A passage from this source also holds items from other sources. Move or delete those items first.'));
        }

        $files = DB::transaction(function () use ($source, $purge) {
            $questionIds = $source->questions()->pluck('id')->all();
            $passages = $source->passages()->with('media')->get();
            $answers = $this->deleteQuestion->answers($questionIds);

            // Passage images: the item snapshots of this source may show them too.
            $passageFiles = $passages->flatMap(fn ($passage) => $passage->media)
                ->map(fn (Media $m) => ['disk' => $m->disk, 'path' => $m->path])->all();
            $files = $this->deleteQuestion->filesOf($answers, $passageFiles, $purge);

            foreach ($source->questions()->get() as $question) {
                $files = [...$files, ...$this->deleteQuestion->remove($question, $answers, $purge)];
            }

            foreach ($passages as $passage) {
                $passage->media()->delete();
                $passage->delete();
            }

            $source->delete();

            Audit::record('source.deleted', $source, meta: [
                'name' => $source->name,
                'questions' => count($questionIds),
                'passages' => $passages->count(),
                'question_ids' => $questionIds,
                'purged' => $purge,
                'purged_answers' => $purge ? $answers->count() : 0,
            ]);

            return $files;
        });

        $this->deleteQuestion->deleteFiles($files);
    }
}
