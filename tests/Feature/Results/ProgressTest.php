<?php

use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Livewire\Progress\Index;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptResult;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Livewire\Livewire;

/**
 * Answer rows for an attempt: one per entry, `true` = correct, `false` = wrong, `null` = unanswered.
 * The question is gone (null id), as after a delete; topic and subtest stay on the row.
 *
 * @param  list<?bool>  $marks
 */
function answersFor(Attempt $attempt, Topic $topic, array $marks, int $from = 1): void
{
    foreach ($marks as $offset => $mark) {
        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => null,
            'position' => $from + $offset,
            'subtest_id' => $topic->subtest_id,
            'topic_id' => $topic->id,
            'snapshot' => ['stem' => 'Q', 'passage' => null, 'choices' => [], 'explanation' => null],
            'correct_key' => 'A',
            'selected_key' => $mark === null ? null : ($mark ? 'A' : 'B'),
            'is_correct' => $mark,
        ]);
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->subtest = Subtest::factory()->create(['name' => 'Verbal Ability', 'position' => 1]);
    $this->grammar = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'name' => 'Grammar', 'position' => 1]);
    $this->vocab = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'name' => 'Vocabulary', 'position' => 2]);
});

it('computes topic accuracy over completed attempts, deleted questions included, abandoned excluded', function () {
    $first = Attempt::factory()->submitted()->create(['user_id' => $this->user->id, 'subtest_id' => $this->subtest->id]);
    answersFor($first, $this->grammar, [true, false, null]);
    answersFor($first, $this->vocab, [true], 4);

    $second = Attempt::factory()->submitted()->create(['user_id' => $this->user->id, 'subtest_id' => $this->subtest->id, 'status' => AttemptStatus::Timeout]);
    answersFor($second, $this->grammar, [true]);

    $abandoned = Attempt::factory()->submitted()->create(['user_id' => $this->user->id, 'subtest_id' => $this->subtest->id, 'status' => AttemptStatus::Abandoned]);
    answersFor($abandoned, $this->grammar, [false, false, false]);
    answersFor($abandoned, $this->vocab, [false], 4);

    $open = Attempt::factory()->create(['user_id' => $this->user->id, 'subtest_id' => $this->subtest->id]);
    answersFor($open, $this->vocab, [false]);

    $other = Attempt::factory()->submitted()->create(['subtest_id' => $this->subtest->id]);
    answersFor($other, $this->vocab, [false, false]);

    $rows = Livewire::test(Index::class)->get('topicAccuracy');

    expect($rows)->toBe([
        ['subtest' => 'Verbal Ability', 'topic' => 'Grammar', 'correct' => 2, 'total' => 4, 'percent' => '50.00'],
        ['subtest' => 'Verbal Ability', 'topic' => 'Vocabulary', 'correct' => 1, 'total' => 1, 'percent' => '100.00'],
    ]);
});

it('lists the history with date, mode, subtest, score and status, abandoned included', function () {
    $done = Attempt::factory()->submitted()->create(['user_id' => $this->user->id, 'subtest_id' => $this->subtest->id, 'finalized_at' => now()->subDay()]);
    AttemptResult::factory()->create(['attempt_id' => $done->id, 'general_rating' => '72.50']);
    $abandoned = Attempt::factory()->submitted()->create([
        'user_id' => $this->user->id, 'mode' => AttemptMode::Combined, 'subtest_id' => null, 'status' => AttemptStatus::Abandoned,
    ]);
    Attempt::factory()->submitted()->create(['subtest_id' => $this->subtest->id]);

    $this->get(route('progress'))
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSeeInOrder([__('Mock exam'), __('Abandoned'), __('Individual test'), 'Verbal Ability', '72.50%', __('Submitted')])
        ->assertSee($done->finalized_at->format('M j, Y'))
        ->assertSee(route('results.show', $done))
        ->assertSee(route('results.show', $abandoned));

    expect(Livewire::test(Index::class)->get('history')->total())->toBe(2);
});

it('shows an empty state for a new user', function () {
    $this->get(route('progress'))
        ->assertSee(__('No attempts yet.'));
});

it('needs sign-in', function () {
    auth()->logout();

    $this->get(route('progress'))->assertRedirect(route('start'));
});
