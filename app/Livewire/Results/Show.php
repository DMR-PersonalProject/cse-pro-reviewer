<?php

namespace App\Livewire\Results;

use App\Domain\Attempts\AttemptClock;
use App\Enums\AttemptMode;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The result of one ended attempt (FR-S5, first version): score, pass or target, time used.
 * Everything comes from the stored snapshot, never from live data.
 */
class Show extends Component
{
    #[Locked]
    public int $attemptId;

    /** Answers left in the browser's autosave stash that the server never stored; shown once. */
    #[Locked]
    public ?int $unsaved = null;

    public function mount(Attempt $attempt, AttemptClock $clock): void
    {
        Gate::authorize('view', $attempt);

        $this->attemptId = $attempt->id;

        // Lazy finalization: an expired attempt becomes a timeout result here too.
        $clock->guard($attempt);

        if ($attempt->fresh()->isInProgress()) {
            $this->redirect(route('attempts.show', $attempt));
        }
    }

    #[Computed]
    public function attempt(): Attempt
    {
        return Attempt::query()->with(['result', 'subtest'])->findOrFail($this->attemptId);
    }

    /**
     * Individual tests show a percentage against the target; the mock exam shows the GR and pass or fail.
     */
    public function isIndividual(): bool
    {
        return $this->attempt->mode === AttemptMode::Individual;
    }

    /**
     * Counts the stashed answers the server refused (spec: offline edge case). Read-only: it
     * never writes an answer, a score or a status. Only the owner, only once the attempt has
     * ended; the list is capped at the item count and anything malformed is skipped.
     *
     * @param  array<mixed>  $pending
     */
    public function replay(array $pending): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->attempt->user_id === $user->id, 403);

        if ($this->attempt->isInProgress()) {
            return;
        }

        $saved = $this->attempt->answers()->get(['position', 'selected_key', 'snapshot'])->keyBy('position');
        $refused = [];

        foreach (array_slice(array_values($pending), 0, $this->attempt->item_count) as $entry) {
            $position = is_array($entry) ? ($entry['position'] ?? null) : null;
            $key = is_array($entry) ? ($entry['key'] ?? null) : null;

            if (! is_int($position) || ! is_string($key) || isset($refused[$position]) || ! $saved->has($position)) {
                continue;
            }

            $answer = $saved[$position];

            if (in_array($key, array_column($answer->snapshot['choices'], 'key'), true) && $key !== $answer->selected_key) {
                $refused[$position] = true;
            }
        }

        $this->unsaved = count($refused);
    }

    public function render(): View
    {
        return view('livewire.results.show')->title(__('Result'));
    }
}
