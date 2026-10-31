<?php

use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Livewire\Attempt\Runner;
use App\Livewire\Results\Show;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

use function Pest\Livewire\livewire;

function startReplayAttempt(User $user, int $count = 5): Attempt
{
    $subtest = Subtest::factory()->create(['code' => 'numerical']);
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count($count)->create(['topic_id' => $topic->id]);

    return app(StartAttempt::class)->individual($user, $subtest, [], $count, AttemptTiming::Timed)->attempt;
}

/**
 * A key that is shown for the item but differs from the one saved (or any key when none is).
 */
function otherKey(Attempt $attempt, int $position): string
{
    $answer = $attempt->answers()->where('position', $position)->first();

    return collect($answer->snapshot['choices'])->pluck('key')->first(fn ($key) => $key !== $answer->selected_key);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->attempt = startReplayAttempt($this->user);
});

it('saves an answer for an explicit position without moving there', function () {
    livewire(Runner::class, ['attempt' => $this->attempt])->call('answer', 3, 'B')->assertSet('position', 1);

    expect($this->attempt->answers()->where('position', 3)->value('selected_key'))->toBe('B');
});

it('saves a replayed answer that arrives within grace', function () {
    $this->travelTo($this->attempt->deadline_at->copy()->addSeconds(5));

    livewire(Runner::class, ['attempt' => $this->attempt])->call('answer', 2, 'C');

    expect($this->attempt->answers()->where('position', 2)->value('selected_key'))->toBe('C')
        ->and($this->attempt->fresh()->isInProgress())->toBeTrue();
});

it('rejects a replayed answer past grace and goes to the results', function () {
    $component = livewire(Runner::class, ['attempt' => $this->attempt]);
    $this->travelTo($this->attempt->deadline_at->copy()->addSeconds(11));

    $component->call('answer', 2, 'C')->assertRedirect(route('results.show', $this->attempt));

    expect($this->attempt->answers()->where('position', 2)->value('selected_key'))->toBeNull()
        ->and($this->attempt->fresh()->status)->toBe(AttemptStatus::Timeout);
});

describe('results replay', function () {
    beforeEach(function () {
        app(SaveAnswer::class)->handle($this->user, $this->attempt, 1, 'A');
        app(FinalizeAttempt::class)->handle($this->attempt, AttemptStatus::Timeout);
        $this->attempt->refresh();
    });

    it('shows how many stashed answers were not saved', function () {
        livewire(Show::class, ['attempt' => $this->attempt])
            ->call('replay', [['position' => 2, 'key' => 'B'], ['position' => 3, 'key' => 'C']])
            ->assertSet('unsaved', 2)
            ->assertSee(__(':count answers were not saved because time ran out.', ['count' => 2]));
    });

    it('does not count an answer that matches the saved one', function () {
        livewire(Show::class, ['attempt' => $this->attempt])
            ->call('replay', [['position' => 1, 'key' => 'A'], ['position' => 2, 'key' => 'B']])
            ->assertSet('unsaved', 1);
    });

    it('never changes a stored answer, score or status', function () {
        $before = [
            $this->attempt->answers()->get(['selected_key', 'is_correct', 'answered_at'])->toArray(),
            $this->attempt->result->toArray(),
            $this->attempt->status,
        ];

        livewire(Show::class, ['attempt' => $this->attempt])
            ->call('replay', [['position' => 1, 'key' => otherKey($this->attempt, 1)], ['position' => 2, 'key' => 'B']]);

        $attempt = $this->attempt->fresh();
        expect([
            $attempt->answers()->get(['selected_key', 'is_correct', 'answered_at'])->toArray(),
            $attempt->result->toArray(),
            $attempt->status,
        ])->toEqual($before);
    });

    it('ignores malformed and unknown entries', function (array $junk) {
        livewire(Show::class, ['attempt' => $this->attempt])
            ->call('replay', [...$junk, ['position' => 3, 'key' => 'B']])
            ->assertSet('unsaved', 1);
    })->with([
        'a string' => [['junk', null, 7]],
        'bad positions' => [[['position' => 'x', 'key' => 'B'], ['position' => 99, 'key' => 'B'], ['position' => 0, 'key' => 'B']]],
        'bad keys' => [[['position' => 2], ['position' => 2, 'key' => ['B']], ['position' => 2, 'key' => 'Q']]],
    ]);

    it('counts a position once', function () {
        livewire(Show::class, ['attempt' => $this->attempt])
            ->call('replay', [['position' => 3, 'key' => 'B'], ['position' => 3, 'key' => 'C']])
            ->assertSet('unsaved', 1);
    });

    it('caps the list at the item count', function () {
        $pending = array_map(fn ($i) => ['position' => 2 + ($i % 4), 'key' => 'B'], range(0, 999));
        $pending[] = ['position' => 1, 'key' => otherKey($this->attempt, 1)];

        // Only the first five entries are read (positions 2..5 and 2 again), so item 1 is never reached.
        livewire(Show::class, ['attempt' => $this->attempt])->call('replay', $pending)->assertSet('unsaved', 4);
    });

    it('shows nothing when every entry was saved', function () {
        livewire(Show::class, ['attempt' => $this->attempt])
            ->call('replay', [['position' => 1, 'key' => 'A']])
            ->assertSet('unsaved', 0)
            ->assertDontSee(__('not saved'));
    });

    it('refuses another user', function () {
        $component = livewire(Show::class, ['attempt' => $this->attempt]);
        $this->actingAs(User::factory()->create());

        $component->call('replay', [['position' => 2, 'key' => 'B']])->assertForbidden();
    });
});

it('counts nothing while the attempt is still in progress', function () {
    $component = livewire(Show::class, ['attempt' => $this->attempt]);

    $component->call('replay', [['position' => 2, 'key' => 'B']])->assertSet('unsaved', null);

    expect($this->attempt->answers()->where('position', 2)->value('selected_key'))->toBeNull();
});
