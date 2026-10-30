<?php

use App\Domain\Content\MarkdownRenderer;
use App\Domain\Content\PublishQuestion;
use App\Enums\Language;
use App\Enums\QuestionStatus;
use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Topic;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleContentSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed([TaxonomySeeder::class, SampleContentSeeder::class]);
    $this->source = Source::where('name', SampleContentSeeder::SOURCE_NAME)->sole();
});

it('uses one public, original source', function () {
    expect(SampleContentSeeder::SOURCE_NAME)->toBe('Original — seed')
        ->and($this->source->visibility)->toBe(SourceVisibility::Public)
        ->and($this->source->type)->toBe(SourceType::Original)
        ->and(Question::where('source_id', '!=', $this->source->id)->exists())->toBeFalse();
});

it('has at least 2 published public items for each of the 17 topics', function () {
    $topics = Topic::withCount(['questions' => fn ($query) => $query
        ->where('status', QuestionStatus::Published)
        ->where('source_id', $this->source->id)])->get();

    expect($topics)->toHaveCount(17);

    foreach ($topics as $topic) {
        expect($topic->questions_count)->toBeGreaterThanOrEqual(2, "Topic {$topic->code} has {$topic->questions_count} items");
    }
});

it('publishes every item with exactly one correct choice and an explanation, passing the publish gates', function () {
    $questions = Question::with('choices')->get();
    $gates = app(PublishQuestion::class);

    expect($questions->count())->toBeGreaterThanOrEqual(34);

    foreach ($questions as $question) {
        expect($question->status)->toBe(QuestionStatus::Published)
            ->and($question->published_at)->not->toBeNull()
            ->and($question->choices->where('is_correct', true))->toHaveCount(1)
            ->and($question->choices->count())->toBeBetween(2, 5)
            ->and(trim((string) $question->explanation))->not->toBe('');

        $question->status = QuestionStatus::Draft; // blockers() also checks the status flow
        expect($gates->blockers($question))->toBe([], "Item #{$question->id}: {$question->stem}");
    }
});

it('has no possible duplicates among the seeded items', function () {
    expect(Question::whereNotNull('duplicate_of_question_id')->count())->toBe(0);
});

it('includes a published reading passage with 3 ordered items', function () {
    $passage = Passage::with('questions')->where('source_id', $this->source->id)->sole();

    expect($passage->topic->code)->toBe('reading_comprehension')
        ->and($passage->status)->toBe(QuestionStatus::Published)
        ->and(trim((string) $passage->body))->not->toBe('')
        ->and($passage->questions)->toHaveCount(3)
        ->and($passage->questions->pluck('passage_position')->all())->toBe([1, 2, 3]);
});

it('includes a data interpretation item with a Markdown table', function () {
    $withTable = Question::whereRelation('topic', 'code', 'data_interpretation')
        ->get()
        ->first(fn (Question $question) => str_contains(app(MarkdownRenderer::class)->render($question->stem), '<table>'));

    expect($withTable)->not->toBeNull();
});

it('includes an abstract reasoning item with a stored PNG figure that has alt text', function () {
    $media = Media::where('mediable_type', (new Question)->getMorphClass())->get();
    $figure = $media->first(fn (Media $item) => $item->mediable->topic->code === 'abstract_reasoning');

    expect($figure)->not->toBeNull()
        ->and($figure->mime)->toBe('image/png')
        ->and(trim((string) $figure->alt_text))->not->toBe('');

    foreach (Media::all() as $item) {
        Storage::disk('public')->assertExists($item->path);
        expect(trim((string) $item->alt_text))->not->toBe('');
    }
});

it('includes Filipino word meaning items', function () {
    expect(Question::whereRelation('topic', 'code', 'word_meaning')->where('language', Language::Fil)->count())
        ->toBeGreaterThanOrEqual(2);
});

it('can run again without adding anything', function () {
    $counts = [Question::count(), Passage::count(), Media::count(), Source::count()];

    $this->seed(SampleContentSeeder::class);

    expect([Question::count(), Passage::count(), Media::count(), Source::count()])->toBe($counts);
});

it('is part of the default database seed, with model events on so stems are normalized', function () {
    Media::query()->delete();
    Question::query()->delete();
    Passage::query()->delete();
    Source::query()->delete();

    $this->seed(DatabaseSeeder::class);

    expect(Question::count())->toBeGreaterThanOrEqual(34)
        ->and(Question::whereNull('stem_normalized')->exists())->toBeFalse();
});
