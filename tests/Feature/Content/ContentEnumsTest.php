<?php

use App\Enums\Difficulty;
use App\Enums\Language;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Enums\SourceType;
use App\Enums\SourceVisibility;

it('has the values from the spec', function (string $enum, array $values) {
    expect(array_map(fn ($case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'source visibility (FR-Q4)' => [SourceVisibility::class, ['private', 'public']],
    'source type (FR-Q4)' => [SourceType::class, ['reviewer', 'book', 'website', 'original', 'other']],
    'difficulty (FR-Q1)' => [Difficulty::class, ['easy', 'medium', 'hard']],
    'language (FR-Q1)' => [Language::class, ['en', 'fil']],
    'needs-review reasons (FR-M4)' => [NeedsReviewReason::class, [
        'missing_answer_key', 'missing_explanation', 'possible_duplicate',
        'low_confidence', 'missing_media', 'flagged_in_source',
    ]],
]);

it('gives every case a readable label', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBe('')->not->toBe($case->value);
    }
})->with([
    QuestionStatus::class, SourceVisibility::class, SourceType::class,
    Difficulty::class, Language::class, NeedsReviewReason::class,
]);

it('labels the languages for people', function () {
    expect(Language::En->label())->toBe('English')
        ->and(Language::Fil->label())->toBe('Filipino');
});

it('defaults sources to private', function () {
    expect(SourceVisibility::default())->toBe(SourceVisibility::Private);
});
