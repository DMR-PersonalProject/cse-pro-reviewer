<?php

namespace App\Livewire;

use App\Domain\Attempts\AbandonAttempt;
use App\Domain\Attempts\AttemptClock;
use App\Domain\Attempts\FinalizeExpiredAttempts;
use App\Enums\AttemptStatus;
use App\Enums\Role;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Home page: PIN nudge, the resume card for a test in progress, and the start button.
 */
class Dashboard extends Component
{
    public function mount(FinalizeExpiredAttempts $sweep): void
    {
        // Lazy finalization (plan §5 rule 5): an expired attempt ends here as timeout.
        $sweep->forUser($this->user);
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function needsPin(): bool
    {
        return $this->user->hasRole(Role::User) && ! $this->user->hasPin();
    }

    #[Computed]
    public function active(): ?Attempt
    {
        return Attempt::query()->with('subtest')
            ->where('user_id', $this->user->id)
            ->where('status', AttemptStatus::InProgress)
            ->first();
    }

    /**
     * Time left as mm:ss, or null when the attempt is untimed.
     */
    public function timeLeft(Attempt $attempt): ?string
    {
        $ms = app(AttemptClock::class)->remainingMs($attempt);

        return $ms === null ? null : AttemptClock::format(intdiv($ms, 1000));
    }

    public function abandon(AbandonAttempt $abandon): void
    {
        if ($this->active !== null) {
            $abandon->handle($this->user, $this->active);
        }

        unset($this->active);
    }

    public function render(): View
    {
        return view('livewire.dashboard')->title(__('Dashboard'));
    }
}
