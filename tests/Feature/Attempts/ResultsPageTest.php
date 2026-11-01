<?php

use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Livewire\Results\Show;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

/**
 * A finalized 5-item attempt with `correct` items answered correctly.
 */
function finishedAttempt(User $user, int $correct, AttemptStatus $end = AttemptStatus::Submitted): Attempt
{
    $subtest = Subtest::factory()->create(['code' => 'numerical', 'name' => 'Numerical Ability']);
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count(5)->create(['topic_id' => $topic->id]);
    $attempt = app(StartAttempt::class)->individual($user, $subtest, [], 5, AttemptTiming::Timed)->attempt;

    foreach ($attempt->answers()->take($correct)->get() as $answer) {
        app(SaveAnswer::class)->handle($user, $attempt, $answer->position, $answer->correct_key);
    }

    app(FinalizeAttempt::class)->handle($attempt, $end);

    return $attempt->fresh();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows the owner the score, the target and the time used', function () {
    $attempt = finishedAttempt($this->user, 4);
    $attempt->result->update(['time_used_seconds' => 754]);

    $this->get(route('results.show', $attempt))
        ->assertOk()
        ->assertSeeLivewire(Show::class)
        ->assertSee('Numerical Ability')
        ->assertSee('80.00%')
        ->assertSee(__('Target 80%: reached'))
        ->assertSee(__('4 of 5 correct'))
        ->assertSee('12:34')
        ->assertSee(__('Submitted'));
});

it('says when the target was not reached', function () {
    $attempt = finishedAttempt($this->user, 3, AttemptStatus::Timeout);

    $this->get(route('results.show', $attempt))
        ->assertSee('60.00%')
        ->assertSee(__('Target 80%: not reached'))
        ->assertSee(__('Time ran out'));
});

it('refuses another user with 403', function () {
    $attempt = finishedAttempt(User::factory()->create(), 4);

    $this->get(route('results.show', $attempt))->assertForbidden();
});

it('redirects an in-progress attempt to the runner', function () {
    $subtest = Subtest::factory()->create();
    Question::factory()->public()->published()->count(3)
        ->create(['topic_id' => Topic::factory()->create(['subtest_id' => $subtest->id])->id]);
    $attempt = app(StartAttempt::class)->individual($this->user, $subtest, [], 3, AttemptTiming::Timed)->attempt;

    $this->get(route('results.show', $attempt))->assertRedirect(route('attempts.show', $attempt));
});

it('finalizes an expired attempt before showing it', function () {
    $subtest = Subtest::factory()->create();
    Question::factory()->public()->published()->count(3)
        ->create(['topic_id' => Topic::factory()->create(['subtest_id' => $subtest->id])->id]);
    $attempt = app(StartAttempt::class)->individual($this->user, $subtest, [], 3, AttemptTiming::Timed)->attempt;
    $this->travelTo($attempt->deadline_at->copy()->addMinute());

    $this->get(route('results.show', $attempt))->assertOk()->assertSee(__('Time ran out'));
});
