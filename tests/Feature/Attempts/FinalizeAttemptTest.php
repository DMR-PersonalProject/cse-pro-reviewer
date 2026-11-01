<?php

use App\Domain\Attempts\AbandonAttempt;
use App\Domain\Attempts\AttemptClock;
use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\FinalizeExpiredAttempts;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\AttemptResult;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

function finalizeFixture(User $user, int $count = 10, AttemptTiming $timing = AttemptTiming::Timed): Attempt
{
    $subtest = Subtest::factory()->create();
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count($count)->create(['topic_id' => $topic->id]);

    return app(StartAttempt::class)->individual($user, $subtest, [], $count, $timing)->attempt;
}

function answerCorrectly(User $user, Attempt $attempt, array $positions): void
{
    foreach ($positions as $position) {
        app(SaveAnswer::class)->handle($user, $attempt, $position, $attempt->answers[$position - 1]->correct_key);
    }
}

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->user = User::factory()->create();
});

it('scores a submitted attempt and stores the result and subtest scores', function () {
    $attempt = finalizeFixture($this->user);
    answerCorrectly($this->user, $attempt, [1, 2, 3, 4, 5, 6, 7, 8]);
    $this->travel(4)->minutes();

    $result = app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Submitted);
    $attempt->refresh();
    $score = $attempt->subtestScores->sole();

    expect($result->general_rating)->toBe('80.00')
        ->and($result->passed)->toBeTrue()
        ->and($result->formula_version)->toBe('v1')
        ->and($result->passing_grade)->toBe('80.00')
        ->and($result->correct_count)->toBe(8)
        ->and($result->total_count)->toBe(10)
        ->and($result->end_reason)->toBe(AttemptStatus::Submitted)
        ->and($result->time_used_seconds)->toBe(240)
        ->and($result->topic_breakdown)->toHaveCount(1)
        ->and($attempt->status)->toBe(AttemptStatus::Submitted)
        ->and($attempt->finalized_at)->not->toBeNull()
        ->and($attempt->time_used_seconds)->toBe(240)
        ->and([$score->correct, $score->total, $score->percent])->toBe([8, 10, '80.00']);
});

it('AC-8: finalizing twice gives one result with the same payload', function () {
    $attempt = finalizeFixture($this->user);
    answerCorrectly($this->user, $attempt, [1, 2]);

    $first = app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Submitted);
    $this->travel(1)->minutes();
    $second = app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Timeout);

    expect(AttemptResult::count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->only(['general_rating', 'end_reason', 'time_used_seconds']))
        ->toBe($first->only(['general_rating', 'end_reason', 'time_used_seconds']))
        ->and($attempt->fresh()->subtestScores)->toHaveCount(1);
});

it('caps the time used at the deadline', function () {
    $attempt = finalizeFixture($this->user);
    $this->travelTo($attempt->deadline_at->copy()->addMinutes(30));

    $result = app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Timeout);

    expect($result->time_used_seconds)->toBe((int) $attempt->started_at->diffInSeconds($attempt->deadline_at));
});

it('flags a suspiciously fast attempt, and never when the threshold is 0', function () {
    $fast = finalizeFixture($this->user);
    $this->travel(49)->seconds(); // 10 items at 5 s each = 50 s
    app(FinalizeAttempt::class)->handle($fast, AttemptStatus::Submitted);

    $normal = finalizeFixture($this->user);
    $this->travel(50)->seconds();
    app(FinalizeAttempt::class)->handle($normal, AttemptStatus::Submitted);

    app(Settings::class)->set(SettingKey::SuspiciousSecondsPerItem, 0);
    $off = finalizeFixture($this->user);
    $this->travel(1)->seconds();
    app(FinalizeAttempt::class)->handle($off, AttemptStatus::Submitted);

    expect($fast->fresh()->is_suspicious)->toBeTrue()
        ->and($normal->fresh()->is_suspicious)->toBeFalse()
        ->and($off->fresh()->is_suspicious)->toBeFalse();
});

it('AC-7: the lazy sweep finalizes an expired attempt as timeout and scores the saved answers', function () {
    $attempt = finalizeFixture($this->user);
    answerCorrectly($this->user, $attempt, [1, 2, 3]);
    $this->travelTo($attempt->deadline_at->copy()->addSeconds(11));

    app(FinalizeExpiredAttempts::class)->forUser($this->user);
    $attempt->refresh();

    expect($attempt->status)->toBe(AttemptStatus::Timeout)
        ->and($attempt->result->end_reason)->toBe(AttemptStatus::Timeout)
        ->and($attempt->result->correct_count)->toBe(3)
        ->and($attempt->result->general_rating)->toBe('30.00');
});

it('the guard finalizes only once the grace has passed', function () {
    $attempt = finalizeFixture($this->user);
    $clock = app(AttemptClock::class);

    $this->travelTo($attempt->deadline_at->copy()->addSeconds(10));
    expect($clock->guard($attempt))->toBeFalse()->and($attempt->fresh()->isInProgress())->toBeTrue();

    $this->travel(1)->seconds();
    expect($clock->guard($attempt))->toBeTrue()->and($attempt->fresh()->status)->toBe(AttemptStatus::Timeout);
});

it('abandoning stores a result with the abandoned end reason', function () {
    $attempt = finalizeFixture($this->user);

    app(AbandonAttempt::class)->handle($this->user, $attempt);

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Abandoned)
        ->and($attempt->fresh()->result->end_reason)->toBe(AttemptStatus::Abandoned);
});

it('the command finalizes only expired timed attempts', function () {
    $expired = finalizeFixture($this->user);
    $running = finalizeFixture(User::factory()->create());
    $untimed = finalizeFixture(User::factory()->create(), timing: AttemptTiming::Untimed);
    $running->update(['deadline_at' => now()->addHours(5)]);
    $this->travelTo($expired->deadline_at->copy()->addMinutes(1));

    $this->artisan('cse:finalize-expired')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(AttemptStatus::Timeout)
        ->and($running->fresh()->isInProgress())->toBeTrue()
        ->and($untimed->fresh()->isInProgress())->toBeTrue();
});
