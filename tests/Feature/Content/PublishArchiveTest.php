<?php

use App\Domain\Content\ArchiveQuestion;
use App\Domain\Content\Exceptions\QuestionNotPublishable;
use App\Domain\Content\PublishQuestion;
use App\Domain\Content\UnarchiveQuestion;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Models\AuditLog;
use App\Models\Passage;
use App\Models\Question;
use App\Models\User;

function addMedia($model, ?string $alt): void
{
    $model->media()->create(['disk' => 'public', 'path' => 'media/x.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => $alt]);
}

function publishBlockers(Question $question): array
{
    try {
        app(PublishQuestion::class)->handle($question);
    } catch (QuestionNotPublishable $e) {
        return $e->blockers;
    }

    return [];
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('publishes a complete draft and audits it', function () {
    $question = Question::factory()->withChoices()->create();

    app(PublishQuestion::class)->handle($question);

    $question->refresh();
    expect($question->status)->toBe(QuestionStatus::Published)
        ->and($question->published_at)->not->toBeNull()
        ->and($question->archived_at)->toBeNull()
        ->and(AuditLog::where('action', 'question.published')->where('subject_id', $question->id)->exists())->toBeTrue();
});

it('blocks an item in needs_review and lists the reasons (AC-24)', function () {
    $question = Question::factory()->withChoices()->create([
        'status' => QuestionStatus::NeedsReview,
        'needs_review_reasons' => [NeedsReviewReason::MissingExplanation, NeedsReviewReason::PossibleDuplicate],
    ]);

    expect(publishBlockers($question))
        ->toContain(NeedsReviewReason::MissingExplanation->label())
        ->toContain(NeedsReviewReason::PossibleDuplicate->label());
    expect($question->fresh()->status)->toBe(QuestionStatus::NeedsReview);
});

it('blocks an item with no correct choice', function () {
    $question = Question::factory()->withChoices(4, correct: 0)->create();

    expect(publishBlockers($question))->toContain(__('No correct choice.'));
});

it('blocks an item with more than one correct choice', function () {
    $question = Question::factory()->withChoices()->create();
    $question->choices()->where('position', 2)->update(['is_correct' => true]);

    expect(publishBlockers($question))->toContain(__('More than one correct choice.'));
});

it('blocks an item with fewer than 2 or more than 5 choices', function (int $count) {
    $question = Question::factory()->withChoices($count)->create();

    expect(publishBlockers($question))->toContain(__('An item needs 2–5 choices.'));
})->with([0, 1, 6]);

it('blocks media without alt text on the item, a choice or its passage', function (string $where) {
    $passage = Passage::factory()->create();
    $question = Question::factory()->inPassage($passage)->withChoices()->create();

    addMedia(match ($where) {
        'question' => $question,
        'choice' => $question->choices()->first(),
        'passage' => $passage,
    }, '  ');

    expect(publishBlockers($question))->toContain(__('An image has no alt text.'));
})->with(['question', 'choice', 'passage']);

it('blocks an item whose passage has no text and no media', function () {
    $passage = Passage::factory()->create(['body' => null]);
    $question = Question::factory()->inPassage($passage)->withChoices()->create();

    expect(publishBlockers($question))->toContain(__('The passage has no text and no image.'));

    addMedia($passage, 'A chart');
    expect(publishBlockers($question->fresh()))->toBe([]);
});

it('lists every blocker at once and changes nothing', function () {
    $question = Question::factory()->create([
        'status' => QuestionStatus::NeedsReview,
        'needs_review_reasons' => [NeedsReviewReason::MissingAnswerKey],
    ]);

    expect(publishBlockers($question))->toHaveCount(3)
        ->and(AuditLog::count())->toBe(0);
});

it('reports blockers without throwing, for bulk actions', function () {
    $ok = Question::factory()->withChoices()->create();
    $bad = Question::factory()->create();

    expect(app(PublishQuestion::class)->blockers($ok))->toBe([])
        ->and(app(PublishQuestion::class)->blockers($bad))->not->toBe([]);
});

it('refuses to publish an item that is already published', function () {
    $question = Question::factory()->published()->create();

    expect(publishBlockers($question))->toContain(__('Only draft or archived items can be published.'));
});

it('archives a published item and audits it', function () {
    $question = Question::factory()->published()->create();

    app(ArchiveQuestion::class)->handle($question);

    $question->refresh();
    expect($question->status)->toBe(QuestionStatus::Archived)
        ->and($question->archived_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'question.archived')->where('subject_id', $question->id)->exists())->toBeTrue();
});

it('archives only published items', function (QuestionStatus $status) {
    $question = Question::factory()->withChoices()->create(['status' => $status]);

    app(ArchiveQuestion::class)->handle($question);
})->with([QuestionStatus::Draft, QuestionStatus::NeedsReview, QuestionStatus::Archived])
    ->throws(DomainException::class);

it('publishes again after archiving', function () {
    $question = Question::factory()->published()->create();
    app(ArchiveQuestion::class)->handle($question);

    app(PublishQuestion::class)->handle($question->fresh());

    expect($question->fresh())
        ->status->toBe(QuestionStatus::Published)
        ->archived_at->toBeNull();
});

it('unarchives through the publish gates and audits it', function () {
    $question = Question::factory()->published()->create();
    app(ArchiveQuestion::class)->handle($question);

    app(UnarchiveQuestion::class)->handle($question->fresh());

    expect($question->fresh()->status)->toBe(QuestionStatus::Published)
        ->and(AuditLog::where('action', 'question.unarchived')->exists())->toBeTrue();
});

it('unarchives only archived items', function () {
    app(UnarchiveQuestion::class)->handle(Question::factory()->withChoices()->create());
})->throws(DomainException::class);
