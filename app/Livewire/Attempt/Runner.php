<?php

namespace App\Livewire\Attempt;

use App\Domain\Attempts\AttemptClock;
use App\Domain\Attempts\Exceptions\AnswerRefused;
use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\FinalizeExpiredAttempts;
use App\Domain\Attempts\RevealStudyAnswer;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\ToggleFlag;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The test runner (plan §4.1). Public state is only the attempt ID and position: snapshots and
 * keys stay on the server, and an answer is rendered only once it may be revealed (AC-10a).
 */
class Runner extends Component
{
    #[Locked]
    public int $attemptId;

    #[Locked]
    public int $position = 1;

    /** Why the last action was refused, shown to the user. */
    public ?string $notice = null;

    public function mount(Attempt $attempt, FinalizeExpiredAttempts $sweep): void
    {
        abort_unless($attempt->user_id === Auth::id(), 403);

        $sweep->forUser($this->user);

        $this->attemptId = $attempt->id;
        $this->position = $attempt->current_position;

        if (! $attempt->fresh()->isInProgress()) {
            $this->toResults();
        }
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function attempt(): Attempt
    {
        return Attempt::query()->with('subtest')->findOrFail($this->attemptId);
    }

    #[Computed]
    public function item(): AttemptAnswer
    {
        return $this->attempt->answers()->where('position', $this->position)->firstOrFail();
    }

    /**
     * Status only: no content and no keys, so it is safe to send.
     *
     * @return list<array{position: int, answered: bool, flagged: bool, subtest: int}>
     */
    #[Computed]
    public function navigator(): array
    {
        return $this->attempt->answers()
            ->get(['position', 'selected_key', 'flagged', 'subtest_id'])
            ->map(fn (AttemptAnswer $answer) => [
                'position' => $answer->position,
                'answered' => $answer->selected_key !== null,
                'flagged' => $answer->flagged,
                'subtest' => $answer->subtest_id,
            ])
            ->all();
    }

    /**
     * The key and explanation may be shown once the item is revealed in study mode, or after
     * the attempt ends.
     */
    public function mayReveal(AttemptAnswer $item): bool
    {
        return $item->revealed_at !== null || ! $this->attempt->isInProgress();
    }

    public function select(string $key, SaveAnswer $save, RevealStudyAnswer $reveal): void
    {
        $this->answer($this->position, $key, $save, $reveal);
    }

    /**
     * The autosave queue's call: the position travels with the answer, so a stashed answer
     * replays to the right item. The server decides whether it is still accepted (plan §4.1).
     * False means the attempt has ended: the client keeps the answer stashed so the results
     * page can count it as not saved.
     */
    public function answer(int $position, string $key, SaveAnswer $save, RevealStudyAnswer $reveal): bool
    {
        if (! $this->guard()) {
            return false;
        }

        try {
            $this->attempt->timing === AttemptTiming::Study
                ? $reveal->handle($this->user, $this->attempt, $position, $key)
                : $save->handle($this->user, $this->attempt, $position, $key);
        } catch (AnswerRefused $e) {
            $this->notice = $e->getMessage();
        }

        return true;
    }

    public function toggleFlag(ToggleFlag $toggle): void
    {
        if ($this->guard()) {
            $toggle->handle($this->user, $this->attempt, $this->position);
        }
    }

    public function goTo(int $position): void
    {
        if (! $this->guard() || $position < 1 || $position > $this->attempt->item_count) {
            return;
        }

        $this->position = $position;
        $this->attempt->update(['current_position' => $position]);
    }

    public function next(): void
    {
        $this->goTo($this->position + 1);
    }

    public function prev(): void
    {
        $this->goTo($this->position - 1);
    }

    public function submit(FinalizeAttempt $finalize): void
    {
        if ($this->guard()) {
            $finalize->handle($this->attempt, AttemptStatus::Submitted);
            $this->toResults();
        }
    }

    /**
     * The client's clock reached zero. Only server time counts: an early call is ignored and
     * gets the real remaining time back (plan §5 rule 4).
     */
    public function timeUp(AttemptClock $clock, FinalizeAttempt $finalize): ?int
    {
        if (! $this->guard()) {
            return 0;
        }

        if ($clock->hasReachedDeadline($this->attempt)) {
            $finalize->handle($this->attempt, AttemptStatus::Timeout);
            $this->toResults();

            return 0;
        }

        return $clock->remainingMs($this->attempt);
    }

    /**
     * The 60-second keep-alive: finalizes an expired attempt, and the render resyncs the clock.
     */
    public function sync(): void
    {
        $this->guard();
    }

    /**
     * Server time only: remaining time when timed (null otherwise) and elapsed time.
     *
     * @return array{remainingMs: ?int, elapsedMs: int}
     */
    public function clock(): array
    {
        return [
            'remainingMs' => app(AttemptClock::class)->remainingMs($this->attempt),
            'elapsedMs' => (int) $this->attempt->started_at->diffInMilliseconds(now()),
        ];
    }

    /**
     * mm:ss, or h:mm:ss from one hour; the clock store uses the same format.
     */
    public function clockText(int $ms): string
    {
        $seconds = intdiv($ms, 1000);
        $text = sprintf('%02d:%02d', intdiv($seconds % 3600, 60), $seconds % 60);

        return $seconds >= 3600 ? intdiv($seconds, 3600).':'.$text : $text;
    }

    /**
     * Every response resyncs the client clock (plan §5).
     */
    public function rendering(): void
    {
        $this->dispatch('clock-sync', ...$this->clock());
    }

    /**
     * Runs before every action: the owner only, and an ended or expired attempt goes to results.
     */
    private function guard(): bool
    {
        $this->notice = null;
        unset($this->attempt, $this->item, $this->navigator);

        abort_unless($this->attempt->user_id === $this->user->id, 403);

        if (! $this->attempt->isInProgress() || app(AttemptClock::class)->guard($this->attempt)) {
            $this->toResults();

            return false;
        }

        return true;
    }

    private function toResults(): void
    {
        $this->redirect(route('results.show', $this->attemptId));
    }

    public function render(): View
    {
        return view('livewire.attempt.runner')->title(__('Test'));
    }
}
