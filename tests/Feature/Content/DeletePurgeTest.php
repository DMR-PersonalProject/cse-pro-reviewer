<?php

use App\Domain\Attempts\FinalizeAttempt;
use App\Domain\Attempts\SaveAnswer;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Content\DeleteQuestion;
use App\Domain\Content\DeleteSource;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AuditLog;
use App\Models\Choice;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

function addFile(Question|Passage|Choice $model, string $path): void
{
    Storage::disk('public')->put($path, 'img');
    $model->media()->create(['disk' => 'public', 'path' => $path, 'mime' => 'image/png', 'size' => 3, 'alt_text' => 'Alt']);
}

/**
 * A finished attempt over two questions of one source: item 1 has stem and choice images.
 *
 * @return array{attempt: Attempt, question: Question, other: Question, source: Source}
 */
function purgeScenario(User $user): array
{
    $subtest = Subtest::factory()->create();
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    $source = Source::factory()->public()->create();
    [$question, $other] = Question::factory()->public()->published()->count(2)
        ->create(['topic_id' => $topic->id, 'source_id' => $source->id])->all();
    addFile($question, 'media/stem.png');
    addFile($question->choices()->first(), 'media/choice.png');

    $attempt = app(StartAttempt::class)->individual($user, $subtest, [], 2, AttemptTiming::Timed)->attempt;

    foreach ($attempt->answers as $answer) {
        app(SaveAnswer::class)->handle($user, $attempt, $answer->position, $answer->correct_key);
    }

    app(FinalizeAttempt::class)->handle($attempt, AttemptStatus::Submitted);

    return ['attempt' => $attempt->fresh(), 'question' => $question, 'other' => $other, 'source' => $source];
}

function answerFor(Attempt $attempt, Question $question): AttemptAnswer
{
    return $attempt->answers()->get()->first(fn (AttemptAnswer $a) => $a->snapshot['stem'] === $question->stem)
        ?? throw new RuntimeException('No answer for the question.');
}

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->user = User::factory()->create();
    ['attempt' => $this->attempt, 'question' => $this->question, 'other' => $this->other, 'source' => $this->source] = purgeScenario($this->user);
    $this->answer = answerFor($this->attempt, $this->question);
    $this->snapshot = $this->answer->snapshot;
});

it('AC-15: without purge the snapshot stays intact and its files are kept', function () {
    app(DeleteQuestion::class)->handle($this->question, (string) $this->question->id);

    $answer = $this->answer->fresh();
    expect($answer->snapshot)->toEqual($this->snapshot)
        ->and($answer->question_id)->toBeNull()
        ->and($answer->purged_at)->toBeNull();
    Storage::disk('public')->assertExists(['media/stem.png', 'media/choice.png']);
});

it('deletes files no snapshot references when deleting without purge', function () {
    $question = Question::factory()->public()->published()->create();
    addFile($question, 'media/unused.png');

    app(DeleteQuestion::class)->handle($question, (string) $question->id);

    Storage::disk('public')->assertMissing('media/unused.png');
});

it('AC-15: purge replaces the content, sets purged_at and keeps the score', function () {
    $result = $this->attempt->result->toArray();
    $scores = $this->attempt->subtestScores()->get()->toArray();

    app(DeleteQuestion::class)->handle($this->question, (string) $this->question->id, purge: true);

    $answer = $this->answer->fresh();
    $snapshot = $answer->snapshot;
    $placeholder = DeleteQuestion::PLACEHOLDER;

    expect($answer->purged_at)->not->toBeNull()
        ->and($snapshot['stem'])->toBe($placeholder)
        ->and($snapshot['media'])->toBe([])
        ->and($snapshot['explanation'])->toBe($placeholder)
        ->and(collect($snapshot['choices'])->pluck('key')->all())->toBe(collect($this->snapshot['choices'])->pluck('key')->all())
        ->and(collect($snapshot['choices'])->pluck('body')->unique()->all())->toBe([$placeholder])
        ->and(collect($snapshot['choices'])->pluck('media')->flatten()->all())->toBe([])
        ->and(json_encode($snapshot))->not->toContain($this->question->stem)
        ->and([$answer->correct_key, $answer->selected_key, $answer->is_correct, $answer->subtest_id, $answer->topic_id])
        ->toBe([$this->answer->correct_key, $this->answer->selected_key, $this->answer->is_correct, $this->answer->subtest_id, $this->answer->topic_id])
        ->and($this->attempt->result->fresh()->toArray())->toEqual($result)
        ->and($this->attempt->subtestScores()->get()->toArray())->toEqual($scores);

    Storage::disk('public')->assertMissing(['media/stem.png', 'media/choice.png']);
});

it('purge leaves the other items of the attempt alone', function () {
    $otherAnswer = answerFor($this->attempt, $this->other);

    app(DeleteQuestion::class)->handle($this->question, (string) $this->question->id, purge: true);

    expect($otherAnswer->fresh()->snapshot)->toEqual($otherAnswer->snapshot)
        ->and($otherAnswer->fresh()->purged_at)->toBeNull();
});

it('purge replaces the passage copy but keeps files the bank still uses', function () {
    $passage = Passage::factory()->create(['source_id' => $this->source->id]);
    addFile($passage, 'media/passage.png');
    $this->answer->update(['snapshot' => [...$this->snapshot, 'passage' => ['body' => 'Passage text', 'media' => [['disk' => 'public', 'path' => 'media/passage.png']]]]]);

    app(DeleteQuestion::class)->handle($this->question, (string) $this->question->id, purge: true);

    expect($this->answer->fresh()->snapshot['passage'])->toBe(['body' => DeleteQuestion::PLACEHOLDER, 'media' => []]);
    Storage::disk('public')->assertExists('media/passage.png');
});

it('records in the audit meta whether a purge was done', function (bool $purge) {
    app(DeleteQuestion::class)->handle($this->question, (string) $this->question->id, purge: $purge);

    $meta = AuditLog::where('action', 'question.deleted')->sole()->meta;
    expect($meta['purged'])->toBe($purge)
        ->and($meta['purged_answers'])->toBe($purge ? 1 : 0);
})->with([true, false]);

it('purges every item of a deleted source', function () {
    app(DeleteSource::class)->handle($this->source, $this->source->name, purge: true);

    expect($this->attempt->answers()->whereNull('purged_at')->count())->toBe(0)
        ->and(AuditLog::where('action', 'source.deleted')->sole()->meta)->toMatchArray(['purged' => true, 'purged_answers' => 2]);
    Storage::disk('public')->assertMissing(['media/stem.png', 'media/choice.png']);
});

it('keeps snapshots of a deleted source without purge', function () {
    app(DeleteSource::class)->handle($this->source, $this->source->name);

    expect($this->attempt->answers()->whereNotNull('purged_at')->count())->toBe(0)
        ->and(AuditLog::where('action', 'source.deleted')->sole()->meta['purged'])->toBeFalse();
    Storage::disk('public')->assertExists(['media/stem.png', 'media/choice.png']);
});

it('offers the purge option in the delete dialog, off by default', function () {
    livewire(EditQuestion::class, ['record' => $this->question->getRouteKey()])
        ->mountAction('delete')
        ->assertActionDataSet(['purge' => false])
        ->setActionData(['confirmation' => (string) $this->question->id, 'purge' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($this->answer->fresh()->purged_at)->not->toBeNull();
});
