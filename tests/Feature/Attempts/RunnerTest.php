<?php

use App\Domain\Attempts\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Livewire\Attempt\Runner;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

function startRunnerAttempt(User $user, AttemptTiming $timing, int $count = 20): Attempt
{
    $subtest = Subtest::factory()->create(['code' => 'numerical']);
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count($count)
        ->state(new Sequence(fn (Sequence $sequence) => ['explanation' => 'Explanation-'.($sequence->index + 1).'-end']))
        ->create(['topic_id' => $topic->id]);

    return app(StartAttempt::class)->individual($user, $subtest, [], $count, $timing)->attempt;
}

/**
 * Everything the browser receives for this component: the HTML and the Livewire snapshot.
 */
function runnerPayload(Testable $component): string
{
    return $component->html().json_encode($component->snapshot);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('opens at /attempts/{id} for the owner', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);

    $this->get('/attempts/'.$attempt->id)->assertOk()->assertSeeLivewire(Runner::class);
});

it('refuses someone else\'s attempt with 403', function () {
    $attempt = startRunnerAttempt(User::factory()->create(), AttemptTiming::Timed, 5);

    $this->get('/attempts/'.$attempt->id)->assertForbidden();
});

it('redirects a finalized attempt to its results', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);
    $attempt->update(['status' => AttemptStatus::Submitted]);

    $this->get('/attempts/'.$attempt->id)->assertRedirect('/attempts/'.$attempt->id.'/results');
});

it('AC-10a: a timed or untimed attempt sends no answer data', function (AttemptTiming $timing) {
    $attempt = startRunnerAttempt($this->user, $timing);
    $component = livewire(Runner::class, ['attempt' => $attempt]);

    foreach (range(1, 20) as $position) {
        $component->call('goTo', $position);
        $payload = runnerPayload($component);

        expect($payload)->not->toContain('Explanation-')
            ->not->toContain('correct_key')
            ->not->toContain(__('Correct answer'));
    }
})->with([AttemptTiming::Timed, AttemptTiming::Untimed]);

it('AC-10a: a study attempt sends the key and explanation for submitted items only', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Study);
    $component = livewire(Runner::class, ['attempt' => $attempt]);

    foreach ([1, 2, 3] as $position) {
        $component->call('goTo', $position)->call('select', 'B');
    }

    foreach (range(1, 20) as $position) {
        $component->call('goTo', $position);
        $payload = runnerPayload($component);
        $answer = $attempt->answers()->where('position', $position)->first();
        $explanation = $answer->snapshot['explanation'];

        expect($payload)->not->toContain('correct_key');

        if ($position <= 3) {
            expect($payload)->toContain($explanation)->toContain(__('Correct answer'));
        } else {
            expect($payload)->not->toContain('Explanation-')->not->toContain(__('Correct answer'));
        }

        // Only the current item's explanation, never another item's.
        expect(substr_count($payload, 'Explanation-'))->toBe($position <= 3 ? substr_count($payload, $explanation) : 0);
    }
});

it('AC-10: in study mode a choice reveals the answer and can\'t be changed', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Study, 5);
    $answer = $attempt->answers()->where('position', 1)->first();

    livewire(Runner::class, ['attempt' => $attempt])
        ->call('select', 'C')
        ->assertSee($answer->snapshot['explanation'])
        ->assertSee(__('Correct answer'))
        ->call('select', 'D')
        ->assertSee(__('This answer is already final.'));

    expect($answer->fresh()->selected_key)->toBe('C')->and($answer->fresh()->revealed_at)->not->toBeNull();
});

it('saves a selection on the server', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);

    livewire(Runner::class, ['attempt' => $attempt])->call('goTo', 2)->call('select', 'B');

    $answer = $attempt->answers()->where('position', 2)->first();
    expect($answer->selected_key)->toBe('B')->and($answer->answered_at)->not->toBeNull();
});

it('navigates and remembers the current item', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);

    livewire(Runner::class, ['attempt' => $attempt])
        ->call('next')->assertSet('position', 2)
        ->call('next')->assertSet('position', 3)
        ->call('prev')->assertSet('position', 2)
        ->call('goTo', 5)->call('next')->assertSet('position', 5)
        ->call('goTo', 99)->assertSet('position', 5);

    expect($attempt->fresh()->current_position)->toBe(5);
    livewire(Runner::class, ['attempt' => $attempt->fresh()])->assertSet('position', 5);
});

it('toggles a flag and shows it in the navigator', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);

    livewire(Runner::class, ['attempt' => $attempt])
        ->call('toggleFlag')
        ->assertSeeHtml('data-flagged="1"');

    expect($attempt->answers()->where('position', 1)->first()->flagged)->toBeTrue();
});

it('locks the attempt id', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);

    livewire(Runner::class, ['attempt' => $attempt])->set('attemptId', 999);
})->throws(CannotUpdateLockedPropertyException::class);

it('ignores an early timeUp and returns the remaining time', function () {
    $this->freezeSecond();
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);
    $this->travel(5)->minutes();

    $component = livewire(Runner::class, ['attempt' => $attempt])->call('timeUp');

    $component->assertReturned(($attempt->deadline_at->getTimestamp() - now()->getTimestamp()) * 1000);
    expect($attempt->fresh()->status)->toBe(AttemptStatus::InProgress);
});

it('finalizes on timeUp once the deadline has passed', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);
    $component = livewire(Runner::class, ['attempt' => $attempt]);
    $this->travelTo($attempt->deadline_at);

    $component->call('timeUp')->assertRedirect('/attempts/'.$attempt->id.'/results');
    expect($attempt->fresh()->status)->toBe(AttemptStatus::Timeout);
});

it('redirects any action on an expired attempt to the results', function (string $action, array $args) {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);
    $component = livewire(Runner::class, ['attempt' => $attempt]);
    $this->travelTo($attempt->deadline_at->addMinute());

    $component->call($action, ...$args)->assertRedirect('/attempts/'.$attempt->id.'/results');
    expect($attempt->fresh()->status)->toBe(AttemptStatus::Timeout)
        ->and($attempt->answers()->whereNotNull('selected_key')->count())->toBe(0);
})->with([
    'select' => ['select', ['A']],
    'toggleFlag' => ['toggleFlag', []],
    'goTo' => ['goTo', [2]],
    'next' => ['next', []],
    'submit' => ['submit', []],
    'sync' => ['sync', []],
]);

it('submits and redirects to the results', function () {
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Untimed, 5);

    livewire(Runner::class, ['attempt' => $attempt])
        ->call('select', 'A')
        ->call('submit')
        ->assertRedirect('/attempts/'.$attempt->id.'/results');

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Submitted);
});

it('sends the remaining time with every response', function () {
    $this->freezeSecond();
    $attempt = startRunnerAttempt($this->user, AttemptTiming::Timed, 5);

    livewire(Runner::class, ['attempt' => $attempt])
        ->call('next')
        ->assertDispatched('clock-sync', remainingMs: 6 * 60 * 1000, elapsedMs: 0);
});
