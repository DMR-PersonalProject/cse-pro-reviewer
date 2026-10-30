<?php

use App\Domain\Content\PublishQuestions;
use App\Domain\Content\ResolveReviewReason;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\User;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

function inReview(array $reasons): Question
{
    return Question::factory()->withChoices()->create([
        'status' => QuestionStatus::NeedsReview,
        'needs_review_reasons' => $reasons,
    ]);
}

function editPage(Question $question)
{
    return livewire(EditQuestion::class, ['record' => $question->getRouteKey()]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('publishes from the edit page', function () {
    $question = Question::factory()->withChoices()->create();

    editPage($question)->callAction('publish')->assertNotified(__('Published'));

    expect($question->fresh()->status)->toBe(QuestionStatus::Published);
});

it('shows why an item can\'t be published', function () {
    $question = Question::factory()->withChoices(4, correct: 0)->create();

    editPage($question)
        ->callAction('publish')
        ->assertNotified(__('Can’t publish yet'));

    expect($question->fresh()->status)->toBe(QuestionStatus::Draft);
});

it('shows the publish, archive and unarchive actions only when they apply', function () {
    $draft = Question::factory()->withChoices()->create();
    editPage($draft)->assertActionVisible('publish')->assertActionHidden('archive')->assertActionHidden('unarchive');

    $published = Question::factory()->published()->create();
    editPage($published)->assertActionHidden('publish')->assertActionVisible('archive')->assertActionHidden('unarchive');

    $published->update(['status' => QuestionStatus::Archived]);
    editPage($published)->assertActionHidden('publish')->assertActionHidden('archive')->assertActionVisible('unarchive');
});

it('archives and unarchives from the edit page', function () {
    $question = Question::factory()->published()->create();

    editPage($question)->callAction('archive');
    expect($question->fresh()->status)->toBe(QuestionStatus::Archived);

    editPage($question->fresh())->callAction('unarchive');
    expect($question->fresh()->status)->toBe(QuestionStatus::Published);
});

it('bulk-publishes, skipping blocked items and reporting why', function () {
    $ready = Question::factory()->count(2)->withChoices()->create();
    $blocked = Question::factory()->withChoices(4, correct: 0)->create();
    $review = inReview([NeedsReviewReason::MissingExplanation]);

    $result = app(PublishQuestions::class)->handle(collect([...$ready, $blocked, $review]));

    expect($result->published)->toHaveCount(2)
        ->and($result->skipped)->toHaveKeys([$blocked->id, $review->id])
        ->and($result->skipped[$blocked->id])->toContain(__('No correct choice.'))
        ->and($result->skipped[$review->id])->toContain(NeedsReviewReason::MissingExplanation->label());

    expect($ready->each->refresh()->pluck('status')->unique()->all())->toBe([QuestionStatus::Published])
        ->and($blocked->fresh()->status)->toBe(QuestionStatus::Draft);
});

it('has a bulk publish action in the table that reports skipped items', function () {
    $ready = Question::factory()->withChoices()->create();
    $blocked = Question::factory()->withChoices(4, correct: 0)->create();

    livewire(ListQuestions::class)
        ->callTableBulkAction('publish', [$ready, $blocked])
        ->assertNotified(__('Published 1 of 2 items'));

    expect($ready->fresh()->status)->toBe(QuestionStatus::Published)
        ->and($blocked->fresh()->status)->toBe(QuestionStatus::Draft);
});

it('has bulk archive and unarchive actions that skip items they don\'t apply to', function () {
    $published = Question::factory()->published()->create();
    $draft = Question::factory()->withChoices()->create();

    livewire(ListQuestions::class)
        ->callTableBulkAction('archive', [$published, $draft])
        ->assertNotified(__('Archived 1 of 2 items'));
    expect($published->fresh()->status)->toBe(QuestionStatus::Archived)
        ->and($draft->fresh()->status)->toBe(QuestionStatus::Draft);

    livewire(ListQuestions::class)
        ->callTableBulkAction('unarchive', [$published->fresh(), $draft])
        ->assertNotified(__('Unarchived 1 of 2 items'));
    expect($published->fresh()->status)->toBe(QuestionStatus::Published);
});

it('has single-record publish and archive actions in the table', function () {
    $question = Question::factory()->withChoices()->create();

    livewire(ListQuestions::class)->callTableAction('publish', $question);
    expect($question->fresh()->status)->toBe(QuestionStatus::Published);

    livewire(ListQuestions::class)->callTableAction('archive', $question->fresh());
    expect($question->fresh()->status)->toBe(QuestionStatus::Archived);
});

it('lists the review reasons in a panel, with a Resolve action for each', function () {
    $question = inReview([NeedsReviewReason::MissingExplanation, NeedsReviewReason::PossibleDuplicate]);

    editPage($question)
        ->assertSee(NeedsReviewReason::MissingExplanation->label())
        ->assertSee(NeedsReviewReason::PossibleDuplicate->label())
        ->assertActionVisible(TestAction::make('resolve_missing_explanation')->schemaComponent('needs_review_actions'))
        ->assertActionVisible(TestAction::make('resolve_possible_duplicate')->schemaComponent('needs_review_actions'))
        ->assertActionDoesNotExist(TestAction::make('resolve_low_confidence')->schemaComponent('needs_review_actions'));
});

it('lets the item be published once the last reason is resolved', function () {
    $question = inReview([NeedsReviewReason::MissingExplanation, NeedsReviewReason::PossibleDuplicate]);

    editPage($question)->callAction(TestAction::make('resolve_missing_explanation')->schemaComponent('needs_review_actions'));
    $question->refresh();
    expect($question->status)->toBe(QuestionStatus::NeedsReview)
        ->and($question->needs_review_reasons->all())->toBe([NeedsReviewReason::PossibleDuplicate]);

    editPage($question)->callAction(TestAction::make('resolve_possible_duplicate')->schemaComponent('needs_review_actions'));
    $question->refresh();
    expect($question->status)->toBe(QuestionStatus::Draft)
        ->and($question->needs_review_reasons)->toBeEmpty();

    editPage($question)->callAction('publish');
    expect($question->fresh()->status)->toBe(QuestionStatus::Published);
});

it('audits each resolved reason', function () {
    $question = inReview([NeedsReviewReason::MissingAnswerKey]);

    app(ResolveReviewReason::class)->handle($question, NeedsReviewReason::MissingAnswerKey);

    $log = AuditLog::where('action', 'question.review_resolved')->sole();
    expect($log->subject_id)->toBe($question->id)
        ->and($log->meta)->toBe(['reason' => 'missing_answer_key', 'remaining' => []]);
});

it('ignores a reason the item doesn\'t have', function () {
    $question = inReview([NeedsReviewReason::MissingAnswerKey]);

    app(ResolveReviewReason::class)->handle($question, NeedsReviewReason::LowConfidence);

    expect($question->fresh()->needs_review_reasons->all())->toBe([NeedsReviewReason::MissingAnswerKey])
        ->and(AuditLog::count())->toBe(0);
});
