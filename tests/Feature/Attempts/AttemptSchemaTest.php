<?php

use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptResult;
use App\Models\AttemptSubtestScore;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('nulls question_id and keeps the snapshot when the question is deleted', function () {
    $answer = AttemptAnswer::factory()->create();
    $stem = $answer->snapshot['stem'];

    $answer->question->delete();

    $answer->refresh();
    expect($answer->question_id)->toBeNull()
        ->and($answer->snapshot['stem'])->toBe($stem)
        ->and($answer->correct_key)->toBe('A');
});

it('casts an attempt and links its answers, result and subtest scores', function () {
    $attempt = Attempt::factory()->submitted()->create(['topic_ids' => [3, 4]]);
    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'position' => 2]);
    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'position' => 1]);
    AttemptResult::factory()->create(['attempt_id' => $attempt->id]);
    AttemptSubtestScore::factory()->create(['attempt_id' => $attempt->id, 'subtest_id' => $attempt->subtest_id]);

    $attempt->refresh();
    expect($attempt->mode)->toBe(AttemptMode::Individual)
        ->and($attempt->timing)->toBe(AttemptTiming::Timed)
        ->and($attempt->status)->toBe(AttemptStatus::Submitted)
        ->and($attempt->topic_ids)->toBe([3, 4])
        ->and($attempt->config['grace_seconds'])->toBe(10)
        ->and($attempt->answers->pluck('position')->all())->toBe([1, 2])
        ->and($attempt->result->general_rating)->toBe('85.00')
        ->and($attempt->result->end_reason)->toBe(AttemptStatus::Submitted)
        ->and($attempt->subtestScores)->toHaveCount(1)
        ->and($attempt->user->attempts)->toHaveCount(1);
});

it('refuses two answers at the same position', function () {
    $attempt = Attempt::factory()->create();
    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'position' => 1]);

    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'position' => 1]);
})->throws(UniqueConstraintViolationException::class);

it('deletes answers and scores with their attempt', function () {
    $attempt = Attempt::factory()->submitted()->create();
    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id]);
    AttemptResult::factory()->create(['attempt_id' => $attempt->id]);
    AttemptSubtestScore::factory()->create(['attempt_id' => $attempt->id]);

    $attempt->delete();

    expect(AttemptAnswer::count())->toBe(0)
        ->and(AttemptResult::count())->toBe(0)
        ->and(AttemptSubtestScore::count())->toBe(0);
});

it('lets the owner update an in-progress attempt only', function () {
    $attempt = Attempt::factory()->create();
    $owner = $attempt->user;

    expect($owner->can('update', $attempt))->toBeTrue()
        ->and(User::factory()->create()->can('update', $attempt))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('update', $attempt))->toBeFalse();

    $attempt->update(['status' => AttemptStatus::Submitted]);

    expect($owner->can('update', $attempt))->toBeFalse();
});

it('lets the owner and an admin view an attempt, but not other users', function () {
    $attempt = Attempt::factory()->submitted()->create();

    expect($attempt->user->can('view', $attempt))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('view', $attempt))->toBeTrue()
        ->and(User::factory()->create()->can('view', $attempt))->toBeFalse();
});
