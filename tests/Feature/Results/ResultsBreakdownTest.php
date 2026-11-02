<?php

use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Leaderboard\IneligibleReason;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

/**
 * A finalized 6-item numerical attempt over two topics, 4 answered correctly.
 */
function scoredAttempt(User $user): Attempt
{
    $subtest = Subtest::factory()->create(['code' => 'numerical', 'name' => 'Numerical Ability']);

    foreach (['Word Problems', 'Number Series'] as $position => $name) {
        $topic = Topic::factory()->create(['subtest_id' => $subtest->id, 'name' => $name, 'position' => $position + 1]);
        Question::factory()->public()->published()->count(3)->create(['topic_id' => $topic->id]);
    }

    $attempt = app(StartAttempt::class)->individual($user, $subtest, [], 6, AttemptTiming::Timed)->attempt;

    foreach ($attempt->answers()->take(4)->get() as $answer) {
        app(SaveAnswer::class)->handle($user, $attempt, $answer->position, $answer->correct_key);
    }

    app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Submitted);

    return $attempt->fresh();
}

beforeEach(function () {
    $this->user = User::factory()->withPin()->create();
    $this->actingAs($this->user);
});

it('shows the subtest and topic tables from the stored snapshot, not recalculated', function () {
    $attempt = scoredAttempt($this->user);
    $topics = Topic::query()->orderBy('position')->get();

    // Overwrite the stored numbers: the page must show these, proving it never rescores.
    $attempt->subtestScores()->update(['correct' => 5, 'total' => 6, 'percent' => '83.33']);
    $attempt->result->update(['topic_breakdown' => [
        $topics[0]->id => ['correct' => 1, 'total' => 3, 'percent' => '33.33'],
        $topics[1]->id => ['correct' => 2, 'total' => 3, 'percent' => '66.67'],
    ]]);

    $this->get(route('results.show', $attempt))
        ->assertOk()
        ->assertSeeInOrder([__('By subtest'), 'Numerical Ability', '5 / 6', '83.33%'])
        ->assertSeeInOrder([__('By topic'), 'Word Problems', '1 / 3', '33.33%', 'Number Series', '2 / 3', '66.67%']);
});

it('says when the attempt counts toward the leaderboard', function () {
    $attempt = scoredAttempt($this->user);
    $attempt->update(['is_standard' => true]);

    $this->get(route('results.show', $attempt))
        ->assertSee(__('Counts toward the leaderboard'))
        ->assertDontSee(__('Not on leaderboard'));
});

it('renders the eligibility reason for each case', function (array $attempt, array $user, IneligibleReason $reason) {
    $model = scoredAttempt($this->user);
    $model->update(['is_standard' => true, ...$attempt]);
    $this->user->update($user);

    $this->get(route('results.show', $model))
        ->assertSee($reason->label())
        ->assertDontSee(__('Counts toward the leaderboard'));
})->with([
    'study' => [['timing' => AttemptTiming::Study], [], IneligibleReason::Study],
    'untimed' => [['timing' => AttemptTiming::Untimed], [], IneligibleReason::Untimed],
    'partial' => [['is_partial' => true], [], IneligibleReason::Partial],
    'not standard' => [['is_standard' => false], [], IneligibleReason::NotStandard],
    'private items' => [['has_private_items' => true], [], IneligibleReason::PrivateItems],
    'hidden' => [['hidden_at' => now(), 'hidden_reason' => 'Test'], [], IneligibleReason::Hidden],
    'no PIN' => [[], ['password' => null], IneligibleReason::NoPin],
    'abandoned' => [['status' => AttemptStatus::Abandoned], [], IneligibleReason::NotCompleted],
]);

it('labels each breakdown table region once, not twice (M7-02)', function () {
    $attempt = scoredAttempt($this->user);

    $html = $this->get(route('results.show', $attempt))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="'.__('By subtest').'"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="'.__('By topic').'"'))->toBe(1);
});
