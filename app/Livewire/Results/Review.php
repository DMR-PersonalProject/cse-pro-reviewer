<?php

namespace App\Livewire\Results;

use App\Domain\Attempts\AttemptClock;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Item-by-item review of an ended attempt (FR-S6). Reads the answer snapshots only, never the
 * live question, so later edits or deletes don't change it (AC-14, AC-15).
 */
class Review extends Component
{
    use WithPagination;

    public const FILTERS = ['all', 'wrong', 'flagged', 'unanswered'];

    #[Locked]
    public int $attemptId;

    #[Url]
    public string $filter = 'all';

    public function mount(Attempt $attempt, AttemptClock $clock): void
    {
        Gate::authorize('view', $attempt);

        $this->attemptId = $attempt->id;
        $clock->guard($attempt);

        // Keys and explanations of an open attempt must never be shown.
        if ($attempt->fresh()->isInProgress()) {
            $this->redirect(route('attempts.show', $attempt));
        }
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AttemptAnswer>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $attempt = Attempt::query()->findOrFail($this->attemptId);

        // Never show an open attempt's answers, even through a direct Livewire call.
        abort_if($attempt->isInProgress(), 403);

        return AttemptAnswer::query()
            ->with('subtest')
            ->where('attempt_id', $this->attemptId)
            ->when($this->filter === 'wrong', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('is_correct')->orWhere('is_correct', false)))
            ->when($this->filter === 'flagged', fn (Builder $query) => $query->where('flagged', true))
            ->when($this->filter === 'unanswered', fn (Builder $query) => $query->whereNull('selected_key'))
            ->orderBy('position')
            ->paginate(20);
    }

    public function render(): View
    {
        if (! in_array($this->filter, self::FILTERS, true)) {
            $this->filter = 'all';
        }

        return view('livewire.results.review')->title(__('Review'));
    }
}
