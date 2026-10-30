<?php

use App\Models\Question;
use App\Models\Topic;

const SHARED_STEM = 'Which of the following best describes the main idea of the passage about rice farming in the Cordillera?';

beforeEach(function () {
    $this->topic = Topic::factory()->create();
    $this->original = Question::factory()->create([
        'subtest_id' => $this->topic->subtest_id, 'topic_id' => $this->topic->id, 'stem' => SHARED_STEM,
    ]);
});

function sameSubtest(Topic $topic, array $attributes = []): Question
{
    return Question::factory()->create(['subtest_id' => $topic->subtest_id, 'topic_id' => $topic->id, ...$attributes]);
}

it('records a possible duplicate when a question is created', function () {
    $copy = sameSubtest($this->topic, ['stem' => SHARED_STEM]);

    expect($copy->duplicate_of_question_id)->toBe($this->original->id)
        ->and((float) $copy->duplicate_similarity)->toBe(1.0)
        ->and($copy->duplicateOf->is($this->original))->toBeTrue();
});

it('recomputes when the stem is edited', function () {
    $question = sameSubtest($this->topic, ['stem' => 'Something entirely different about tax rates?']);
    expect($question->duplicate_of_question_id)->toBeNull();

    $question->update(['stem' => SHARED_STEM]);
    expect($question->fresh()->duplicate_of_question_id)->toBe($this->original->id);

    $question->update(['stem' => 'Back to something unique about provincial budgets?']);
    expect($question->fresh())
        ->duplicate_of_question_id->toBeNull()
        ->duplicate_similarity->toBeNull();
});

it('recomputes when the subtest changes', function () {
    $elsewhere = Topic::factory()->create();
    $copy = sameSubtest($this->topic, ['stem' => SHARED_STEM]);

    $copy->update(['subtest_id' => $elsewhere->subtest_id, 'topic_id' => $elsewhere->id]);

    expect($copy->fresh()->duplicate_of_question_id)->toBeNull();
});

it('does not recompute when other fields change', function () {
    $copy = sameSubtest($this->topic, ['stem' => SHARED_STEM]);
    $this->original->delete();
    expect($copy->fresh()->duplicate_of_question_id)->toBeNull();

    // The match is gone, but only a stem or subtest edit re-runs the check.
    $copy->update(['explanation' => 'Updated explanation']);
    expect($copy->fresh()->duplicate_of_question_id)->toBeNull();
});

it('never matches itself', function () {
    $this->original->update(['stem' => SHARED_STEM.' ']);

    expect($this->original->fresh()->duplicate_of_question_id)->toBeNull();
});
