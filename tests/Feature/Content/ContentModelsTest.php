<?php

use App\Enums\Language;
use App\Enums\QuestionStatus;
use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\Choice;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('defaults a new source to private, in the model and in the database', function () {
    $source = Source::create(['name' => 'Reviewer X (2024)', 'type' => SourceType::Reviewer]);
    expect($source->visibility)->toBe(SourceVisibility::Private);

    DB::table('sources')->insert(['name' => 'Raw insert', 'type' => 'book', 'created_at' => now(), 'updated_at' => now()]);
    expect(Source::where('name', 'Raw insert')->value('visibility'))->toBe(SourceVisibility::Private);
});

it('orders a question\'s choices by position and letters them A–E', function () {
    $question = Question::factory()->create();
    foreach ([3, 1, 2] as $position) {
        $question->choices()->create(['position' => $position, 'body' => "Choice {$position}", 'is_correct' => $position === 2]);
    }

    $choices = $question->fresh()->choices;

    expect($choices->pluck('position')->all())->toBe([1, 2, 3])
        ->and($choices->map->key()->all())->toBe(['A', 'B', 'C'])
        ->and($question->fresh()->correctChoice()->key())->toBe('B');
});

it('rejects two choices in the same position', function () {
    $question = Question::factory()->create();
    $question->choices()->create(['position' => 1, 'body' => 'A', 'is_correct' => true]);
    $question->choices()->create(['position' => 1, 'body' => 'B', 'is_correct' => false]);
})->throws(UniqueConstraintViolationException::class);

it('orders a passage\'s questions by passage_position', function () {
    $passage = Passage::factory()->create();
    Question::factory()->inPassage($passage, 2)->create(['stem' => 'second']);
    Question::factory()->inPassage($passage, 1)->create(['stem' => 'first']);
    Question::factory()->inPassage($passage, 3)->create(['stem' => 'third']);

    expect($passage->fresh()->questions->pluck('stem')->all())->toBe(['first', 'second', 'third']);
});

it('deletes a question\'s choices and tag links with it', function () {
    $question = Question::factory()->withChoices()->create();
    $question->tags()->attach(Tag::create(['name' => 'tricky']));

    $question->delete();

    expect(Choice::count())->toBe(0)
        ->and(DB::table('question_tag')->count())->toBe(0)
        ->and(Tag::count())->toBe(1);
});

it('has a published() factory state with exactly one correct choice', function () {
    $question = Question::factory()->published()->create();

    expect($question->status)->toBe(QuestionStatus::Published)
        ->and($question->published_at)->not->toBeNull()
        ->and($question->choices)->toHaveCount(4)
        ->and($question->choices->where('is_correct', true))->toHaveCount(1);
});

it('has private() and public() factory states', function () {
    expect(Question::factory()->private()->create()->source->visibility)->toBe(SourceVisibility::Private)
        ->and(Question::factory()->public()->create()->source->visibility)->toBe(SourceVisibility::Public);
});

it('has an inPassage() state that copies the passage\'s subtest and topic', function () {
    $passage = Passage::factory()->create();

    $question = Question::factory()->inPassage($passage)->create();

    expect($question->passage->is($passage))->toBeTrue()
        ->and($question->subtest_id)->toBe($passage->subtest_id)
        ->and($question->topic_id)->toBe($passage->topic_id)
        ->and($question->passage_position)->toBe(1);
});

it('starts a question as an English draft with no review reasons', function () {
    $question = Question::factory()->create()->fresh();

    expect($question->status)->toBe(QuestionStatus::Draft)
        ->and($question->language)->toBe(Language::En)
        ->and($question->needs_review_reasons)->toBeEmpty()
        ->and($question->lock_choice_order)->toBeFalse();
});

it('attaches media to questions, passages and choices, ordered by position', function () {
    $question = Question::factory()->withChoices()->create();
    $passage = Passage::factory()->create();

    $question->media()->create(['disk' => 'public', 'path' => 'media/b.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => 'B', 'position' => 2]);
    $question->media()->create(['disk' => 'public', 'path' => 'media/a.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => 'A', 'position' => 1]);
    $passage->media()->create(['disk' => 'public', 'path' => 'media/c.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => 'C', 'position' => 1]);
    $question->choices->first()->media()->create(['disk' => 'public', 'path' => 'media/d.png', 'mime' => 'image/png', 'size' => 10, 'alt_text' => 'D', 'position' => 1]);

    expect($question->fresh()->media->pluck('alt_text')->all())->toBe(['A', 'B'])
        ->and($passage->fresh()->media)->toHaveCount(1)
        ->and(Media::where('mediable_type', (new Choice)->getMorphClass())->count())->toBe(1)
        ->and(Media::first()->mediable)->toBeInstanceOf(Question::class);
});

it('lists a source\'s questions and passages', function () {
    $source = Source::factory()->create();
    Question::factory()->count(2)->for($source)->create();
    Passage::factory()->for($source)->create();

    expect($source->questions()->count())->toBe(2)
        ->and($source->passages()->count())->toBe(1);
});
