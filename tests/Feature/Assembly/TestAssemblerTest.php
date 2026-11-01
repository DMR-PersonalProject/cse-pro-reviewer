<?php

use App\Domain\Assembly\AssemblyRequest;
use App\Domain\Assembly\TestAssembler;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

function assembleFor(User $user, Subtest $subtest, int $count, array $topicIds = [], int $seed = 42)
{
    return app(TestAssembler::class)->assemble(new AssemblyRequest($user, $subtest->id, $topicIds, $count, $seed));
}

function publicItems(Topic $topic, int $count)
{
    return Question::factory()->public()->published()->count($count)->create(['topic_id' => $topic->id]);
}

function publicPassage(Topic $topic, int $items): Passage
{
    $passage = Passage::factory()->create([
        'subtest_id' => $topic->subtest_id,
        'topic_id' => $topic->id,
        'source_id' => Source::factory()->public(),
    ]);

    for ($position = 1; $position <= $items; $position++) {
        Question::factory()->inPassage($passage, $position)->published()->create(['source_id' => Source::factory()->public()]);
    }

    return $passage;
}

beforeEach(function () {
    $this->subtest = Subtest::factory()->create();
    $this->user = User::factory()->create();
});

it('AC-5: gives 20 distinct items from the chosen topic', function () {
    $sequences = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    $other = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    publicItems($sequences, 30);
    publicItems($other, 10);

    $result = assembleFor($this->user, $this->subtest, 20, [$sequences->id]);
    $questions = $result->questions();

    expect($questions)->toHaveCount(20)
        ->and($questions->pluck('id')->unique())->toHaveCount(20)
        ->and($questions->pluck('topic_id')->unique()->all())->toBe([$sequences->id])
        ->and($result->shortfall())->toBe(0);
});

it('AC-9: keeps a passage\'s items together, in authored order, counting each item', function () {
    $topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    $passage = publicPassage($topic, 3);
    publicItems($topic, 2);

    $result = assembleFor($this->user, $this->subtest, 5);
    $ids = $result->questions()->pluck('id')->all();
    $passageIds = $passage->questions()->orderBy('passage_position')->pluck('id')->all();
    $start = array_search($passageIds[0], $ids, true);

    expect($ids)->toHaveCount(5)
        ->and(array_slice($ids, $start, 3))->toBe($passageIds);
});

it('skips a passage that does not fit and fills with smaller units', function () {
    $topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    publicPassage($topic, 4);
    publicItems($topic, 3);

    $result = assembleFor($this->user, $this->subtest, 3);

    expect($result->questions())->toHaveCount(3)
        ->and($result->questions()->whereNotNull('passage_id'))->toBeEmpty();
});

it('gives the same order for the same seed and a different one for another seed', function () {
    $topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    publicItems($topic, 30);

    $first = assembleFor($this->user, $this->subtest, 20, seed: 7)->questions()->pluck('id')->all();
    $again = assembleFor($this->user, $this->subtest, 20, seed: 7)->questions()->pluck('id')->all();
    $other = assembleFor($this->user, $this->subtest, 20, seed: 8)->questions()->pluck('id')->all();

    expect($again)->toBe($first)->and($other)->not->toBe($first);
});

it('splits the count evenly across topics, the remainder to the first by position', function () {
    $first = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'position' => 1]);
    $second = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'position' => 2]);
    $third = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'position' => 3]);
    foreach ([$first, $second, $third] as $topic) {
        publicItems($topic, 10);
    }

    $counts = assembleFor($this->user, $this->subtest, 10)->questions()->countBy('topic_id');

    expect($counts[$first->id])->toBe(4)
        ->and($counts[$second->id])->toBe(3)
        ->and($counts[$third->id])->toBe(3);
});

it('fills a topic\'s shortfall from the other topics', function () {
    $small = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'position' => 1]);
    $large = Topic::factory()->create(['subtest_id' => $this->subtest->id, 'position' => 2]);
    publicItems($small, 2);
    publicItems($large, 20);

    $result = assembleFor($this->user, $this->subtest, 10);
    $counts = $result->questions()->countBy('topic_id');

    expect($counts[$small->id])->toBe(2)
        ->and($counts[$large->id])->toBe(8)
        ->and($result->shortfall())->toBe(0);
});

it('reports the shortfall when the pool is too small', function () {
    $topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    publicItems($topic, 6);

    $result = assembleFor($this->user, $this->subtest, 20);

    expect($result->questions())->toHaveCount(6)
        ->and($result->requested)->toBe(20)
        ->and($result->shortfall())->toBe(14);
});

it('avoids a repeat when alternatives exist and uses the oldest seen when they don\'t', function () {
    $topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    [$older, $newer, $fresh] = publicItems($topic, 3);
    foreach ([[$older, '2026-10-01 09:00:00'], [$newer, '2026-10-02 09:00:00']] as [$question, $at]) {
        $attempt = Attempt::factory()->submitted()->create(['user_id' => $this->user->id, 'started_at' => $at]);
        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id, 'question_id' => $question->id, 'selected_key' => 'A', 'answered_at' => $at,
        ]);
    }

    expect(assembleFor($this->user, $this->subtest, 1)->questions()->pluck('id')->all())->toBe([$fresh->id])
        ->and(assembleFor($this->user, $this->subtest, 2)->questions()->pluck('id')->sort()->values()->all())
        ->toBe([$older->id, $fresh->id]);
});
