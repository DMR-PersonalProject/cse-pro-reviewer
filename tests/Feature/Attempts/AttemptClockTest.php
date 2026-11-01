<?php

use App\Domain\Attempts\AttemptClock;
use App\Enums\AttemptStatus;
use App\Models\Attempt;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->clock = app(AttemptClock::class);
    // 23-minute timed attempt with the factory's 10-second grace.
    $this->attempt = Attempt::factory()->make(['started_at' => now(), 'deadline_at' => now()->addMinutes(23)]);
});

it('gives the remaining time in milliseconds from server time', function () {
    expect($this->clock->remainingMs($this->attempt))->toBe(23 * 60 * 1000);

    $this->travel(5)->minutes();

    expect($this->clock->remainingMs($this->attempt))->toBe(18 * 60 * 1000);
});

it('never gives a negative remaining time', function () {
    $this->travel(30)->minutes();

    expect($this->clock->remainingMs($this->attempt))->toBe(0);
});

it('has no remaining time for untimed and study attempts', function () {
    expect($this->clock->remainingMs(Attempt::factory()->untimed()->make()))->toBeNull()
        ->and($this->clock->remainingMs(Attempt::factory()->study()->make()))->toBeNull();
});

it('accepts answers up to exactly 10 seconds after the deadline', function () {
    $this->travelTo($this->attempt->deadline_at->copy()->addSeconds(10));

    expect($this->clock->acceptsAnswers($this->attempt))->toBeTrue()
        ->and($this->clock->isExpired($this->attempt))->toBeFalse();
});

it('rejects answers and is expired at 11 seconds after the deadline', function () {
    $this->travelTo($this->attempt->deadline_at->copy()->addSeconds(11));

    expect($this->clock->acceptsAnswers($this->attempt))->toBeFalse()
        ->and($this->clock->isExpired($this->attempt))->toBeTrue();
});

it('uses the grace stored on the attempt, not the current setting', function () {
    $attempt = Attempt::factory()->make([
        'deadline_at' => now(),
        'config' => ['minutes' => 23, 'passing_grade' => 80.0, 'grace_seconds' => 0, 'suspicious_seconds_per_item' => 5],
    ]);
    $this->travel(1)->seconds();

    expect($this->clock->acceptsAnswers($attempt))->toBeFalse();
});

it('reaches the deadline only at or after it, so an early time-up is ignored', function () {
    $this->travelTo($this->attempt->deadline_at->copy()->subSecond());
    expect($this->clock->hasReachedDeadline($this->attempt))->toBeFalse();

    $this->travelTo($this->attempt->deadline_at);
    expect($this->clock->hasReachedDeadline($this->attempt))->toBeTrue();
});

it('never expires an untimed attempt and refuses answers once it is finalized', function () {
    $untimed = Attempt::factory()->untimed()->make();
    $this->travel(10)->days();

    expect($this->clock->isExpired($untimed))->toBeFalse()
        ->and($this->clock->acceptsAnswers($untimed))->toBeTrue()
        ->and($this->clock->hasReachedDeadline($untimed))->toBeFalse();

    $untimed->status = AttemptStatus::Abandoned;

    expect($this->clock->acceptsAnswers($untimed))->toBeFalse()
        ->and($this->clock->isExpired($untimed))->toBeFalse();
});
