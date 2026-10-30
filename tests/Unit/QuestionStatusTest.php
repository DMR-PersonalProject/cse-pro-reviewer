<?php

use App\Enums\QuestionStatus;

// FR-Q2: draft → needs_review ↔ draft → published → archived, and archived → published.
it('allows exactly the FR-Q2 transitions', function (QuestionStatus $from, QuestionStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with(function () {
    $allowed = [
        'draft>needs_review', 'draft>published',
        'needs_review>draft',
        'published>archived',
        'archived>published',
    ];

    foreach (QuestionStatus::cases() as $from) {
        foreach (QuestionStatus::cases() as $to) {
            $key = "{$from->value}>{$to->value}";
            yield $key => [$from, $to, in_array($key, $allowed, true)];
        }
    }
});

it('lists the allowed targets for each status', function () {
    expect(QuestionStatus::Draft->allowedTransitions())->toBe([QuestionStatus::NeedsReview, QuestionStatus::Published])
        ->and(QuestionStatus::NeedsReview->allowedTransitions())->toBe([QuestionStatus::Draft])
        ->and(QuestionStatus::Published->allowedTransitions())->toBe([QuestionStatus::Archived])
        ->and(QuestionStatus::Archived->allowedTransitions())->toBe([QuestionStatus::Published]);
});

it('has the spec values', function () {
    expect(array_map(fn ($case) => $case->value, QuestionStatus::cases()))
        ->toBe(['draft', 'needs_review', 'published', 'archived']);
});
