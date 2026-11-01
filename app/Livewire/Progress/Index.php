<?php

namespace App\Livewire\Progress;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The signed-in user's attempt history and accuracy per topic (FR-S7).
 */
class Index extends Component
{
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Attempt>
     */
    #[Computed]
    public function history(): LengthAwarePaginator
    {
        return Attempt::query()
            ->with(['subtest', 'result'])
            ->where('user_id', Auth::id())
            ->latest('started_at')
            ->latest('id')
            ->paginate(20);
    }

    /**
     * Correct over total per topic across the user's completed attempts. Rows keep their topic
     * after a question is deleted, so those answers still count. Abandoned attempts don't.
     *
     * @return list<array{subtest: string, topic: string, correct: int, total: int, percent: string}>
     */
    #[Computed]
    public function topicAccuracy(): array
    {
        $completed = fn (Builder $query) => $query->whereHas('attempt', fn (Builder $query) => $query
            ->where('user_id', Auth::id())
            ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Timeout]));

        return Topic::query()
            ->with('subtest')
            ->withCount([
                'attemptAnswers as total' => $completed,
                'attemptAnswers as correct' => fn (Builder $query) => $completed($query)->where('is_correct', true),
            ])
            ->get()
            ->filter(fn (Topic $topic) => $topic->total > 0)
            ->sortBy(fn (Topic $topic) => [$topic->subtest->position, $topic->position])
            ->map(fn (Topic $topic) => [
                'subtest' => $topic->subtest->name,
                'topic' => $topic->name,
                'correct' => (int) $topic->correct,
                'total' => (int) $topic->total,
                'percent' => number_format(round($topic->correct * 100 / $topic->total, 2), 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.progress.index')->title(__('Progress'));
    }
}
