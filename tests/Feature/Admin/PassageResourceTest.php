<?php

use App\Enums\Language;
use App\Filament\Resources\Passages\Pages\CreatePassage;
use App\Filament\Resources\Passages\Pages\EditPassage;
use App\Filament\Resources\Passages\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

function questionsTab(Passage $passage)
{
    return livewire(QuestionsRelationManager::class, ['ownerRecord' => $passage, 'pageClass' => EditPassage::class]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a passage', function () {
    $topic = Topic::factory()->create();
    $source = Source::factory()->create();

    livewire(CreatePassage::class)
        ->fillForm([
            'subtest_id' => $topic->subtest_id,
            'topic_id' => $topic->id,
            'source_id' => $source->id,
            'title' => 'Rice terraces',
            'body' => "The Banaue rice terraces were carved...\n\n| Year | Visitors |\n|---|---|\n| 2023 | 120 |",
            'language' => Language::En->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Passage::sole())
        ->title->toBe('Rice terraces')
        ->topic_id->toBe($topic->id)
        ->source_id->toBe($source->id);
});

it('rejects a topic from another subtest', function () {
    $topic = Topic::factory()->create();

    livewire(CreatePassage::class)
        ->fillForm([
            'subtest_id' => $topic->subtest_id,
            'topic_id' => Topic::factory()->create()->id,
            'source_id' => Source::factory()->create()->id,
            'body' => 'Text',
        ])
        ->call('create')
        ->assertHasFormErrors(['topic_id']);
});

it('lists a passage with 3 questions in passage order', function () {
    $passage = Passage::factory()->create();
    $third = Question::factory()->inPassage($passage, 3)->create();
    $first = Question::factory()->inPassage($passage, 1)->create();
    $second = Question::factory()->inPassage($passage, 2)->create();

    questionsTab($passage)
        ->assertCanSeeTableRecords([$first, $second, $third], inOrder: true);
});

it('reorders questions by drag and drop', function () {
    $passage = Passage::factory()->create();
    [$a, $b, $c] = collect([1, 2, 3])->map(fn ($p) => Question::factory()->inPassage($passage, $p)->create())->all();

    questionsTab($passage)->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

    expect($passage->fresh()->questions->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
        ->and($passage->fresh()->questions->pluck('passage_position')->all())->toBe([1, 2, 3]);
});

it('gives an attached item the passage\'s subtest, topic and the next position', function () {
    $passage = Passage::factory()->create();
    Question::factory()->inPassage($passage)->create();
    $loose = Question::factory()->create();

    questionsTab($passage)->callAction(TestAction::make('associate')->table(), data: ['recordId' => $loose->id]);

    expect($loose->fresh())
        ->passage_id->toBe($passage->id)
        ->subtest_id->toBe($passage->subtest_id)
        ->topic_id->toBe($passage->topic_id)
        ->passage_position->toBe(2);
});

it('keeps the questions in step when the passage moves to another topic', function () {
    $passage = Passage::factory()->create();
    $questions = Question::factory()->count(3)->inPassage($passage)->create();
    $newTopic = Topic::factory()->create();

    livewire(EditPassage::class, ['record' => $passage->getRouteKey()])
        ->fillForm(['subtest_id' => $newTopic->subtest_id, 'topic_id' => $newTopic->id])
        ->call('save')
        ->assertHasNoFormErrors();

    foreach ($questions as $question) {
        expect($question->fresh())
            ->subtest_id->toBe($newTopic->subtest_id)
            ->topic_id->toBe($newTopic->id);
    }
});

it('detaches an item from the passage', function () {
    $passage = Passage::factory()->create();
    $question = Question::factory()->inPassage($passage)->create();

    questionsTab($passage)->callAction(TestAction::make('dissociate')->table($question));

    expect($question->fresh())
        ->passage_id->toBeNull()
        ->passage_position->toBeNull();
});

it('links to a new item that starts in this passage', function () {
    $passage = Passage::factory()->create();

    questionsTab($passage)->assertActionHasUrl(
        TestAction::make('createItem')->table(),
        route('filament.admin.resources.questions.create', ['passage_id' => $passage->id]),
    );

    $this->get(route('filament.admin.resources.questions.create', ['passage_id' => $passage->id]))->assertOk();
    livewire(CreateQuestion::class, ['passage_id' => $passage->id])
        ->assertSchemaStateSet([
            'passage_id' => $passage->id,
            'subtest_id' => $passage->subtest_id,
            'topic_id' => $passage->topic_id,
        ]);
});

it('gives users a 403 and serves admins', function () {
    $passage = Passage::factory()->create();

    $this->get('/admin/passages')->assertOk();
    $this->get("/admin/passages/{$passage->id}/edit")->assertOk();

    $this->actingAs(User::factory()->withPin()->create())->get('/admin/passages')->assertForbidden();
});
