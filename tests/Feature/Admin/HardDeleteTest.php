<?php

use App\Domain\Content\DeleteQuestion;
use App\Domain\Content\DeleteSource;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Sources\Pages\EditSource;
use App\Models\AuditLog;
use App\Models\Choice;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

function withMedia(Question|Passage $model): void
{
    $model->media()->create(['disk' => 'public', 'path' => 'media/x.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => 'Alt']);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('refuses to delete a question when the typed ID is wrong', function (string $typed) {
    $question = Question::factory()->withChoices()->create();

    livewire(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->callAction('delete', data: ['confirmation' => $typed])
        ->assertHasActionErrors(['confirmation']);

    expect(Question::whereKey($question->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'question.deleted')->exists())->toBeFalse();
})->with(['empty' => '', 'wrong id' => '999999', 'with a hash' => '#1x']);

it('deletes a question with its choices, media rows and tag links when the ID is typed', function () {
    $question = Question::factory()->withChoices()->create();
    $question->tags()->attach(Tag::create(['name' => 'keep-me']));
    withMedia($question);
    $question->choices->first()->media()->create(['disk' => 'public', 'path' => 'media/c.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => 'C']);
    $id = $question->id;

    livewire(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->callAction('delete', data: ['confirmation' => (string) $id])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    expect(Question::whereKey($id)->exists())->toBeFalse()
        ->and(Choice::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(DB::table('question_tag')->count())->toBe(0)
        ->and(Tag::count())->toBe(1);

    $log = AuditLog::where('action', 'question.deleted')->sole();
    expect($log->subject_id)->toBe($id)
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->meta)->toHaveKey('stem');
});

it('clears duplicate links that pointed at the deleted question', function () {
    $original = Question::factory()->create(['stem' => 'A long enough stem about the provincial budget of Ilocos Norte?']);
    $copy = Question::factory()->create(['subtest_id' => $original->subtest_id, 'stem' => $original->stem]);
    expect($copy->duplicate_of_question_id)->toBe($original->id);

    app(DeleteQuestion::class)->handle($original, (string) $original->id);

    expect($copy->fresh()->duplicate_of_question_id)->toBeNull();
});

it('checks the confirmation in the action too', function () {
    $question = Question::factory()->create();

    app(DeleteQuestion::class)->handle($question, 'nope');
})->throws(DomainException::class);

it('refuses to delete a source when the typed name is wrong', function (string $typed) {
    $source = Source::factory()->create(['name' => 'Reviewer X (2024)']);
    Question::factory()->for($source)->create();

    livewire(EditSource::class, ['record' => $source->getRouteKey()])
        ->callAction('delete', data: ['confirmation' => $typed])
        ->assertHasActionErrors(['confirmation']);

    expect(Source::count())->toBe(1)->and(Question::count())->toBe(1);
})->with(['empty' => '', 'different case' => 'reviewer x (2024)', 'partial' => 'Reviewer X']);

it('deletes a source with all its items and passages when the name is typed', function () {
    $source = Source::factory()->create(['name' => 'Reviewer X (2024)']);
    $passage = Passage::factory()->for($source)->create();
    withMedia($passage);
    Question::factory()->inPassage($passage)->for($source)->withChoices()->count(2)->create();
    Question::factory()->for($source)->withChoices()->create();
    $other = Question::factory()->withChoices()->create();

    livewire(EditSource::class, ['record' => $source->getRouteKey()])
        ->callAction('delete', data: ['confirmation' => 'Reviewer X (2024)'])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    expect(Source::whereKey($source->id)->exists())->toBeFalse()
        ->and(Question::pluck('id')->all())->toBe([$other->id])
        ->and(Passage::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(Choice::where('question_id', '!=', $other->id)->count())->toBe(0);

    $log = AuditLog::where('action', 'source.deleted')->sole();
    expect($log->subject_id)->toBe($source->id)
        ->and($log->meta)->toMatchArray(['name' => 'Reviewer X (2024)', 'questions' => 3, 'passages' => 1]);
});

it('refuses to delete a source whose passage holds items from another source', function () {
    $source = Source::factory()->create();
    $passage = Passage::factory()->for($source)->create();
    Question::factory()->inPassage($passage)->create();

    app(DeleteSource::class)->handle($source, $source->name);
})->throws(DomainException::class);

it('leaves nothing half-deleted when a source delete fails', function () {
    $source = Source::factory()->create();
    $passage = Passage::factory()->for($source)->create();
    Question::factory()->for($source)->create();
    Question::factory()->inPassage($passage)->create();

    try {
        app(DeleteSource::class)->handle($source, $source->name);
    } catch (DomainException) {
    }

    expect(Source::count())->toBe(2)
        ->and(Question::count())->toBe(2)
        ->and(AuditLog::count())->toBe(0);
});
