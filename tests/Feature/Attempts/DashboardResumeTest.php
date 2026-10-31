<?php

use App\Domain\Attempts\AttemptClock;
use App\Domain\Attempts\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Livewire\Dashboard;
use App\Livewire\Tests\ConfigureIndividual;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

use function Pest\Livewire\livewire;

function dashboardAttempt(User $user, AttemptTiming $timing = AttemptTiming::Timed): Attempt
{
    $subtest = Subtest::factory()->create(['name' => 'Verbal Ability']);
    Question::factory()->public()->published()->count(4)
        ->create(['topic_id' => Topic::factory()->create(['subtest_id' => $subtest->id])->id]);

    return app(StartAttempt::class)->individual($user, $subtest, [], 4, $timing)->attempt;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows a resume card with the time left', function () {
    $attempt = dashboardAttempt($this->user);
    $this->travelTo($attempt->started_at->copy()->addSeconds(30));
    $left = intdiv(app(AttemptClock::class)->remainingMs($attempt), 1000);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Test in progress'))
        ->assertSee('Verbal Ability')
        ->assertSee(sprintf('%02d:%02d', intdiv($left, 60), $left % 60))
        ->assertSee(route('attempts.show', $attempt));
});

it('shows no time left for an untimed attempt', function () {
    dashboardAttempt($this->user, AttemptTiming::Untimed);

    $this->get(route('dashboard'))->assertSee(__('Test in progress'))->assertDontSee(__('Time left'));
});

it('shows no resume card without an attempt in progress', function () {
    $this->get(route('dashboard'))->assertOk()->assertDontSee(__('Test in progress'));
});

it('finalizes an expired attempt when the dashboard loads', function () {
    $attempt = dashboardAttempt($this->user);
    $this->travelTo($attempt->deadline_at->copy()->addMinute());

    $this->get(route('dashboard'))->assertOk()->assertDontSee(__('Test in progress'));

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Timeout)
        ->and($attempt->fresh()->result->end_reason)->toBe(AttemptStatus::Timeout);
});

it('abandons the attempt from the dashboard and frees the slot', function () {
    $attempt = dashboardAttempt($this->user);

    livewire(Dashboard::class)->call('abandon')->assertDontSee(__('Test in progress'));

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Abandoned);

    $subtest = Subtest::factory()->create();
    Question::factory()->public()->published()->count(2)
        ->create(['topic_id' => Topic::factory()->create(['subtest_id' => $subtest->id])->id]);
    expect(app(StartAttempt::class)->individual($this->user, $subtest, [], 2, AttemptTiming::Timed)->attempt)->not->toBeNull();
});

it('lets the setup page start a new test once an expired attempt is swept', function () {
    $attempt = dashboardAttempt($this->user);
    $this->travelTo($attempt->deadline_at->copy()->addMinute());

    livewire(ConfigureIndividual::class)->assertDontSee(__('You already have a test in progress.'));

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Timeout);
});
