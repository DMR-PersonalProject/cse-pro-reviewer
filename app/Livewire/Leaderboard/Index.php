<?php

namespace App\Livewire\Leaderboard;

use App\Domain\Leaderboard\LeaderboardQuery;
use App\Domain\Leaderboard\Period;
use App\Models\Subtest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Public boards (FR-L1): the full mock or one subtest, all time or this week. IGN only.
 */
class Index extends Component
{
    use WithPagination;

    /** `mock` or a subtest code; anything unknown falls back to the mock board. */
    #[Url]
    public string $board = 'mock';

    #[Url]
    public string $period = 'all';

    public function updated(string $property): void
    {
        if (in_array($property, ['board', 'period'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, Subtest>
     */
    #[Computed]
    public function subtests(): Collection
    {
        return Subtest::query()->orderBy('position')->get();
    }

    #[Computed]
    public function selectedSubtest(): ?Subtest
    {
        return $this->subtests->firstWhere('code', $this->board);
    }

    #[Computed]
    public function selectedPeriod(): Period
    {
        return Period::tryFrom($this->period) ?? Period::AllTime;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = app(LeaderboardQuery::class);

        return $this->selectedSubtest
            ? $query->subtest($this->selectedSubtest, $this->selectedPeriod)
            : $query->mock($this->selectedPeriod);
    }

    public function render(): View
    {
        return view('livewire.leaderboard.index')->title(__('Leaderboard'));
    }
}
