<?php

use App\Domain\Attempts\AbandonAttempt;
use App\Domain\Attempts\Exceptions\AnswerRefused;
use App\Domain\Attempts\RevealStudyAnswer;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Attempts\ToggleFlag;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

function startedAttempt(User $user, AttemptTiming $timing = AttemptTiming::Timed): Attempt
{
    $subtest = Subtest::factory()->create();
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count(5)->create(['topic_id' => $topic->id]);

    return app(StartAttempt::class)->individual($user, $subtest, [], 5, $timing)->attempt;
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('saves an answer and lets the user change it', function () {
    $attempt = startedAttempt($this->user);
    $correct = $attempt->answers->first()->correct_key;
    $wrong = $correct === 'A' ? 'B' : 'A';

    app(SaveAnswer::class)->handle($this->user, $attempt, 1, $wrong);
    $answer = app(SaveAnswer::class)->handle($this->user, $attempt, 1, $correct);

    expect($answer->selected_key)->toBe($correct)
        ->and($answer->is_correct)->toBeTrue()
        ->and($answer->answered_at)->not->toBeNull();
});

it('accepts a save within the grace and rejects one after deadline plus grace', function () {
    $attempt = startedAttempt($this->user);
    $this->travelTo($attempt->deadline_at->copy()->addSeconds(10));

    app(SaveAnswer::class)->handle($this->user, $attempt, 1, 'A');

    $this->travelTo($attempt->deadline_at->copy()->addSeconds(11));

    expect(fn () => app(SaveAnswer::class)->handle($this->user, $attempt, 2, 'A'))->toThrow(AnswerRefused::class)
        ->and($attempt->answers()->where('position', 2)->value('selected_key'))->toBeNull();
});

it('refuses to save to someone else\'s attempt', function () {
    $attempt = startedAttempt(User::factory()->create());

    expect(fn () => app(SaveAnswer::class)->handle($this->user, $attempt, 1, 'A'))->toThrow(AuthorizationException::class);
});

it('refuses a key that is not one of the item\'s choices', function () {
    $attempt = startedAttempt($this->user);

    expect(fn () => app(SaveAnswer::class)->handle($this->user, $attempt, 1, 'E'))->toThrow(AnswerRefused::class);
});

it('refuses plain saves in study mode', function () {
    $attempt = startedAttempt($this->user, AttemptTiming::Study);

    expect(fn () => app(SaveAnswer::class)->handle($this->user, $attempt, 1, 'A'))->toThrow(AnswerRefused::class);
});

it('AC-10: in study mode, selects, locks and reveals in one step, then refuses a change', function () {
    $attempt = startedAttempt($this->user, AttemptTiming::Study);

    $answer = app(RevealStudyAnswer::class)->handle($this->user, $attempt, 2, 'B');

    expect($answer->selected_key)->toBe('B')
        ->and($answer->answered_at)->not->toBeNull()
        ->and($answer->revealed_at)->not->toBeNull()
        ->and(fn () => app(RevealStudyAnswer::class)->handle($this->user, $attempt, 2, 'C'))->toThrow(AnswerRefused::class)
        ->and($attempt->answers()->where('position', 2)->value('selected_key'))->toBe('B');
});

it('only reveals in study mode', function () {
    $attempt = startedAttempt($this->user, AttemptTiming::Untimed);

    expect(fn () => app(RevealStudyAnswer::class)->handle($this->user, $attempt, 1, 'A'))->toThrow(AnswerRefused::class);
});

it('toggles the flag on an item', function () {
    $attempt = startedAttempt($this->user);

    expect(app(ToggleFlag::class)->handle($this->user, $attempt, 3)->flagged)->toBeTrue()
        ->and(app(ToggleFlag::class)->handle($this->user, $attempt, 3)->flagged)->toBeFalse();
});

it('abandons an attempt, which then takes no answers or flags', function () {
    $attempt = startedAttempt($this->user);

    app(AbandonAttempt::class)->handle($this->user, $attempt);
    $attempt->refresh();

    expect($attempt->status)->toBe(AttemptStatus::Abandoned)
        ->and($attempt->finalized_at)->not->toBeNull()
        ->and(fn () => app(SaveAnswer::class)->handle($this->user, $attempt, 1, 'A'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ToggleFlag::class)->handle($this->user, $attempt, 1))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AbandonAttempt::class)->handle($this->user, $attempt))->toThrow(AuthorizationException::class);
});

it('rejects a position outside the attempt', function () {
    $attempt = startedAttempt($this->user);

    expect(fn () => app(SaveAnswer::class)->handle($this->user, $attempt, 6, 'A'))->toThrow(AnswerRefused::class);
});
