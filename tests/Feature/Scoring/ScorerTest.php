<?php

use App\Domain\Scoring\Scorer;
use App\Models\AttemptAnswer;

/**
 * Unsaved answers: $correct right ones, $wrong wrong ones and $blank unanswered, in one subtest and topic.
 */
function answers(int $correct, int $wrong = 0, int $blank = 0, int $subtestId = 1, int $topicId = 10): array
{
    $make = fn (?string $selected) => new AttemptAnswer([
        'subtest_id' => $subtestId, 'topic_id' => $topicId, 'correct_key' => 'B', 'selected_key' => $selected,
    ]);

    $many = fn (int $count, ?string $selected) => array_map(fn () => $make($selected), array_fill(0, $count, null));

    return [...$many($correct, 'B'), ...$many($wrong, 'C'), ...$many($blank, null)];
}

it('AC-13: 136 of 170 correct is 80.00 and passed', function () {
    $score = app(Scorer::class)->score(collect(answers(136, 34)), 80.0);

    expect($score->generalRating)->toBe('80.00')
        ->and($score->passed)->toBeTrue()
        ->and($score->correct)->toBe(136)
        ->and($score->total)->toBe(170)
        ->and($score->formulaVersion)->toBe('v1')
        ->and($score->passingGrade)->toBe('80.00');
});

it('AC-13: 135 of 170 correct is 79.41 and failed', function () {
    $score = app(Scorer::class)->score(collect(answers(135, 35)), 80.0);

    expect($score->generalRating)->toBe('79.41')->and($score->passed)->toBeFalse();
});

it('counts an unanswered item as wrong', function () {
    $score = app(Scorer::class)->score(collect(answers(3, 0, 1)), 80.0);

    expect($score->correct)->toBe(3)
        ->and($score->total)->toBe(4)
        ->and($score->generalRating)->toBe('75.00');
});

it('passes at a passing grade with two decimals without float error', function () {
    // 0.29 * 100 is 28.999... as a float; 29 of 100 must still pass at 29.00.
    expect(app(Scorer::class)->score(collect(answers(29, 71)), 0.29 * 100)->passed)->toBeTrue()
        ->and(app(Scorer::class)->score(collect(answers(28, 72)), 28.01)->passed)->toBeFalse();
});

it('breaks the score down by subtest and topic', function () {
    $answers = collect([
        ...answers(3, 1, 0, subtestId: 1, topicId: 10),
        ...answers(1, 1, 0, subtestId: 1, topicId: 11),
        ...answers(0, 0, 2, subtestId: 2, topicId: 20),
    ]);

    $score = app(Scorer::class)->score($answers, 80.0);

    expect($score->generalRating)->toBe('50.00')
        ->and($score->subtests)->toBe([
            1 => ['correct' => 4, 'total' => 6, 'percent' => '66.67'],
            2 => ['correct' => 0, 'total' => 2, 'percent' => '0.00'],
        ])
        ->and($score->topics)->toBe([
            10 => ['correct' => 3, 'total' => 4, 'percent' => '75.00'],
            11 => ['correct' => 1, 'total' => 2, 'percent' => '50.00'],
            20 => ['correct' => 0, 'total' => 2, 'percent' => '0.00'],
        ]);
});

it('scores an empty attempt as 0.00 and failed', function () {
    $score = app(Scorer::class)->score(collect(), 80.0);

    expect($score->generalRating)->toBe('0.00')
        ->and($score->passed)->toBeFalse()
        ->and($score->subtests)->toBe([]);
});
