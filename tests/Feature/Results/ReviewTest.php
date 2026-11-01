<?php

use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Attempts\ToggleFlag;
use App\Domain\Content\DeleteQuestion;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Livewire\Results\Review;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Livewire\Livewire;

/**
 * A finalized 4-item attempt: item 1 correct, item 2 wrong, item 3 unanswered and flagged,
 * item 4 correct.
 */
function reviewAttempt(User $user): Attempt
{
    $subtest = Subtest::factory()->create(['code' => 'verbal', 'name' => 'Verbal Ability']);
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count(4)->create(['topic_id' => $topic->id]);
    $attempt = app(StartAttempt::class)->individual($user, $subtest, [], 4, AttemptTiming::Timed)->attempt;
    $answers = $attempt->answers()->get()->keyBy('position');

    app(SaveAnswer::class)->handle($user, $attempt, 1, $answers[1]->correct_key);
    $wrong = collect($answers[2]->snapshot['choices'])->pluck('key')->first(fn ($key) => $key !== $answers[2]->correct_key);
    app(SaveAnswer::class)->handle($user, $attempt, 2, $wrong);
    app(ToggleFlag::class)->handle($user, $attempt, 3);
    app(SaveAnswer::class)->handle($user, $attempt, 4, $answers[4]->correct_key);
    app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Submitted);

    return $attempt->fresh();
}

function answerAt(Attempt $attempt, int $position): AttemptAnswer
{
    return $attempt->answers()->where('position', $position)->firstOrFail();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->attempt = reviewAttempt($this->user);
});

it('shows every item with the answer, the correct answer and the explanation from the snapshot', function () {
    $item = answerAt($this->attempt, 2);

    $this->get(route('results.review', $this->attempt))
        ->assertOk()
        ->assertSeeLivewire(Review::class)
        ->assertSee($item->snapshot['stem'])
        ->assertSee($item->snapshot['explanation'])
        ->assertSee(__('Your answer'))
        ->assertSee(__('Correct answer'))
        ->assertSee(__('Not answered'));
});

it('AC-14: editing the question after the attempt leaves the review unchanged', function () {
    $item = answerAt($this->attempt, 1);
    $question = Question::query()->findOrFail($item->question_id);
    $question->update(['stem' => 'Edited stem after the attempt', 'explanation' => 'Edited explanation']);
    $question->choices()->update(['is_correct' => false]);

    $this->get(route('results.review', $this->attempt))
        ->assertSee($item->snapshot['stem'])
        ->assertSee($item->snapshot['explanation'])
        ->assertDontSee('Edited stem after the attempt')
        ->assertDontSee('Edited explanation');

    expect($this->attempt->result->fresh()->correct_count)->toBe(2);
});

it('AC-15: a deleted question still shows, with a "removed from bank" note', function () {
    $item = answerAt($this->attempt, 1);
    $this->actingAs(User::factory()->admin()->create());
    app(DeleteQuestion::class)->handle(Question::query()->findOrFail($item->question_id), (string) $item->question_id);
    $this->actingAs($this->user);

    $this->get(route('results.review', $this->attempt))
        ->assertOk()
        ->assertSee($item->snapshot['stem'])
        ->assertSee(__('Removed from bank'));
});

it('AC-15: a purged question shows "Content removed (takedown)" without its text and the GR is unchanged', function () {
    $item = answerAt($this->attempt, 1);
    $rating = $this->attempt->result->general_rating;
    $this->actingAs(User::factory()->admin()->create());
    app(DeleteQuestion::class)->handle(Question::query()->findOrFail($item->question_id), (string) $item->question_id, purge: true);
    $this->actingAs($this->user);

    $this->get(route('results.review', $this->attempt))
        ->assertOk()
        ->assertSee(__('Content removed (takedown)'))
        ->assertDontSee($item->snapshot['stem'])
        ->assertDontSee($item->snapshot['explanation']);

    expect($this->attempt->result->fresh()->general_rating)->toBe($rating);
});

it('filters to wrong, flagged and unanswered items', function (string $filter, array $positions) {
    $component = Livewire::test(Review::class, ['attempt' => $this->attempt])->set('filter', $filter);

    expect($component->get('items')->pluck('position')->all())->toBe($positions);
})->with([
    'all' => ['all', [1, 2, 3, 4]],
    'wrong (unanswered counts as wrong)' => ['wrong', [2, 3]],
    'flagged' => ['flagged', [3]],
    'unanswered' => ['unanswered', [3]],
]);

it('reads the filter from the URL and falls back to all for an unknown value', function () {
    Livewire::withQueryParams(['filter' => 'flagged'])->test(Review::class, ['attempt' => $this->attempt])
        ->assertSet('filter', 'flagged');

    $component = Livewire::withQueryParams(['filter' => 'nope'])->test(Review::class, ['attempt' => $this->attempt]);
    expect($component->get('items')->pluck('position')->all())->toBe([1, 2, 3, 4]);
});

it('paginates by 20', function () {
    $attempt = Attempt::factory()->submitted()->create(['user_id' => $this->user->id, 'item_count' => 25]);
    AttemptAnswer::factory()->count(25)->sequence(fn ($s) => ['position' => $s->index + 1])->create(['attempt_id' => $attempt->id]);

    expect(Livewire::test(Review::class, ['attempt' => $attempt])->get('items')->count())->toBe(20);
});

it('refuses another user with 403', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('results.review', $this->attempt))->assertForbidden();
});

it('redirects an attempt in progress to the runner', function () {
    $open = Attempt::factory()->create(['user_id' => User::factory()->create()->id]);
    $this->actingAs($open->user);

    $this->get(route('results.review', $open))->assertRedirect(route('attempts.show', $open));
});

it('links to the review from the results page', function () {
    $this->get(route('results.show', $this->attempt))->assertSee(route('results.review', $this->attempt));
});
