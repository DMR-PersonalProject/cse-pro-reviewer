<?php

use App\Enums\Difficulty;
use App\Enums\Language;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

const DUPLICATE_STEM = 'Which of the following best describes the main idea of the passage about rice farming in the Cordillera?';

/**
 * @return list<array{body: string, is_correct: bool}>
 */
function choiceRows(int $count = 4, array $correct = [2]): array
{
    return array_map(
        fn (int $i) => ['body' => "Choice {$i}", 'is_correct' => in_array($i, $correct, true)],
        range(1, $count),
    );
}

function questionForm(Topic $topic, Source $source, array $overrides = []): array
{
    return array_merge([
        'subtest_id' => $topic->subtest_id,
        'topic_id' => $topic->id,
        'source_id' => $source->id,
        'stem' => 'What is 12 × 12?',
        'choices' => choiceRows(),
        'explanation' => '12 × 12 = 144.',
        'difficulty' => Difficulty::Easy->value,
        'language' => Language::En->value,
        'lock_choice_order' => false,
        'source_page' => '14',
    ], $overrides);
}

beforeEach(function () {
    $this->undoRepeaterFake = Repeater::fake();
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $this->topic = Topic::factory()->create();
    $this->source = Source::factory()->create();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

it('creates a draft question with ordered choices', function () {
    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source))
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::with('choices')->sole();
    expect($question)
        ->status->toBe(QuestionStatus::Draft)
        ->stem->toBe('What is 12 × 12?')
        ->difficulty->toBe(Difficulty::Easy)
        ->source_page->toBe('14')
        ->and($question->choices->pluck('body')->all())->toBe(['Choice 1', 'Choice 2', 'Choice 3', 'Choice 4'])
        ->and($question->choices->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and($question->correctChoice()->key())->toBe('B');
});

it('requires exactly one correct choice', function (array $correct) {
    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['choices' => choiceRows(4, $correct)]))
        ->call('create')
        ->assertHasFormErrors(['choices']);

    expect(Question::count())->toBe(0);
})->with(['none correct' => [[]], 'two correct' => [[1, 3]]]);

it('requires 2–5 choices', function (int $count) {
    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['choices' => choiceRows($count, [1])]))
        ->call('create')
        ->assertHasFormErrors(['choices']);
})->with([1, 6]);

it('offers only the topics of the chosen subtest', function () {
    $otherTopic = Topic::factory()->create();
    $sibling = Topic::factory()->create(['subtest_id' => $this->topic->subtest_id]);

    livewire(CreateQuestion::class)
        ->fillForm(['subtest_id' => $this->topic->subtest_id])
        ->assertFormFieldExists('topic_id', function (Select $field) use ($otherTopic, $sibling) {
            $options = array_keys($field->getOptions());

            return in_array($this->topic->id, $options) && in_array($sibling->id, $options)
                && ! in_array($otherTopic->id, $options);
        });
});

it('rejects a topic from another subtest', function () {
    $otherTopic = Topic::factory()->create();

    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['topic_id' => $otherTopic->id]))
        ->call('create')
        ->assertHasFormErrors(['topic_id']);
});

it('places the item in a passage with the passage\'s subtest and topic', function () {
    $passage = Passage::factory()->create();
    Question::factory()->inPassage($passage)->create();

    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['passage_id' => $passage->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::latest('id')->first();
    expect($question)
        ->passage_id->toBe($passage->id)
        ->subtest_id->toBe($passage->subtest_id)
        ->topic_id->toBe($passage->topic_id)
        ->passage_position->toBe(2);
});

it('edits a question and reorders its choices', function () {
    $question = Question::factory()->withChoices(4, correct: 1)->create([
        'subtest_id' => $this->topic->subtest_id, 'topic_id' => $this->topic->id, 'source_id' => $this->source->id,
    ]);
    $bodies = $question->choices->pluck('body')->all();

    livewire(EditQuestion::class, ['record' => $question->getRouteKey()])
        ->assertSchemaStateSet(['stem' => $question->stem])
        ->fillForm([
            'stem' => 'Edited stem?',
            'choices' => [
                ['body' => $bodies[3], 'is_correct' => false],
                ['body' => $bodies[0], 'is_correct' => true],
                ['body' => $bodies[1], 'is_correct' => false],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $question->refresh()->load('choices');
    expect($question->stem)->toBe('Edited stem?')
        ->and($question->choices->pluck('body')->all())->toBe([$bodies[3], $bodies[0], $bodies[1]])
        ->and($question->correctChoice()->key())->toBe('B');
});

it('saves tags, creating new ones', function () {
    $existing = Tag::create(['name' => 'arithmetic']);

    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['tags' => [$existing->id]]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Question::sole()->tags->pluck('name')->all())->toBe(['arithmetic']);
});

it('stores an uploaded image with its real type, size and alt text', function () {
    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, [
            'media' => [['path' => [UploadedFile::fake()->image('Chart Final.png', 60, 40)], 'alt_text' => 'A bar chart']],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $media = Question::sole()->media->sole();
    expect($media)
        ->disk->toBe('public')
        ->mime->toBe('image/png')
        ->width->toBe(60)
        ->height->toBe(40)
        ->alt_text->toBe('A bar chart')
        ->and($media->path)->toStartWith('media/')->toEndWith('.png')->not->toContain('Chart');
    Storage::disk('public')->assertExists($media->path);
});

it('rejects an SVG upload and an image without alt text (AC-21a)', function (array $row, string $field) {
    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['media' => [$row]]))
        ->call('create')
        ->assertHasFormErrors(["media.0.{$field}"]);

    expect(Question::count())->toBe(0);
})->with([
    'svg' => [['path' => [UploadedFile::fake()->createWithContent('figure.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')], 'alt_text' => 'Alt'], 'path'],
    'svg named .png' => [['path' => [UploadedFile::fake()->createWithContent('figure.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')], 'alt_text' => 'Alt'], 'path'],
    'no alt text' => [['path' => [UploadedFile::fake()->image('a.png')], 'alt_text' => ''], 'alt_text'],
]);

it('warns about a near-identical stem and records the match', function () {
    $original = Question::factory()->published()->create([
        'subtest_id' => $this->topic->subtest_id, 'topic_id' => $this->topic->id, 'stem' => DUPLICATE_STEM,
    ]);

    livewire(CreateQuestion::class)
        ->fillForm(questionForm($this->topic, $this->source, ['stem' => DUPLICATE_STEM.'!!']))
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Possible duplicate'));

    $question = Question::latest('id')->first();
    expect($question->duplicate_of_question_id)->toBe($original->id)
        ->and((float) $question->duplicate_similarity)->toBeGreaterThanOrEqual(0.9)
        ->and($question->status)->toBe(QuestionStatus::Draft);
});

it('lists questions for admins and filters them', function () {
    $other = Topic::factory()->create();
    $draft = Question::factory()->create(['subtest_id' => $this->topic->subtest_id, 'topic_id' => $this->topic->id]);
    $published = Question::factory()->published()->public()->create(['subtest_id' => $other->subtest_id, 'topic_id' => $other->id]);
    $review = Question::factory()->create([
        'subtest_id' => $other->subtest_id, 'topic_id' => $other->id, 'source_id' => $this->source->id,
        'status' => QuestionStatus::NeedsReview, 'needs_review_reasons' => [NeedsReviewReason::MissingExplanation],
    ]);
    $dupe = Question::factory()->create([
        'subtest_id' => $this->topic->subtest_id, 'topic_id' => $this->topic->id, 'stem' => $draft->stem,
    ]);

    $list = fn () => livewire(ListQuestions::class);

    $list()->assertCanSeeTableRecords([$draft, $published, $review, $dupe]);
    $list()->filterTable('subtest_id', $other->subtest_id)->assertCanSeeTableRecords([$published, $review])->assertCanNotSeeTableRecords([$draft, $dupe]);
    $list()->filterTable('topic_id', $this->topic->id)->assertCanSeeTableRecords([$draft, $dupe])->assertCanNotSeeTableRecords([$published]);
    $list()->filterTable('status', QuestionStatus::Published->value)->assertCanSeeTableRecords([$published])->assertCanNotSeeTableRecords([$draft]);
    $list()->filterTable('source_id', $this->source->id)->assertCanSeeTableRecords([$review])->assertCanNotSeeTableRecords([$draft]);
    $list()->filterTable('visibility', 'public')->assertCanSeeTableRecords([$published])->assertCanNotSeeTableRecords([$draft, $review]);
    $list()->filterTable('needs_review')->assertCanSeeTableRecords([$review])->assertCanNotSeeTableRecords([$draft, $published]);
    $list()->filterTable('possible_duplicate')->assertCanSeeTableRecords([$dupe])->assertCanNotSeeTableRecords([$draft, $published]);
});

it('gives users a 403', function () {
    $this->actingAs(User::factory()->withPin()->create())
        ->get('/admin/questions')
        ->assertForbidden();
});

it('serves the pages to admins', function () {
    $question = Question::factory()->withChoices()->create();

    $this->get('/admin/questions')->assertOk();
    $this->get('/admin/questions/create')->assertOk();
    $this->get("/admin/questions/{$question->id}/edit")->assertOk();
});
