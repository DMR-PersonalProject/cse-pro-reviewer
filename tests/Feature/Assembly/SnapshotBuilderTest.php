<?php

use App\Domain\Assembly\ChoiceShuffler;
use App\Domain\Assembly\SnapshotBuilder;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use Random\Engine\Mt19937;
use Random\Randomizer;

function randomizer(int $seed = 1): Randomizer
{
    return new Randomizer(new Mt19937($seed));
}

it('AC-11: keeps the authored choice order when lock_choice_order is set', function () {
    $question = Question::factory()->published()->create(['lock_choice_order' => true]);
    $question->choices()->where('position', 4)->update(['body' => 'All of the above']);
    $authored = $question->choices()->orderBy('position')->pluck('body')->all();

    foreach (range(1, 20) as $seed) {
        $shown = app(ChoiceShuffler::class)->shuffle($question->fresh(), randomizer($seed));

        expect(array_map(fn ($choice) => $choice->body, $shown))->toBe($authored);
    }
});

it('shuffles unlocked choices, the same way for the same seed', function () {
    $question = Question::factory()->published()->create();
    $authored = $question->choices()->orderBy('position')->pluck('id')->all();

    $orders = collect(range(1, 20))->map(fn ($seed) => array_map(
        fn ($choice) => $choice->id,
        app(ChoiceShuffler::class)->shuffle($question, randomizer($seed)),
    ));
    $again = array_map(fn ($choice) => $choice->id, app(ChoiceShuffler::class)->shuffle($question, randomizer(1)));

    expect($orders->contains(fn ($order) => $order !== $authored))->toBeTrue()
        ->and($again)->toBe($orders[0])
        ->and(collect($orders[0])->sort()->values()->all())->toBe($authored);
});

it('snapshots the stem, choices in shown order, explanation and source visibility', function () {
    $question = Question::factory()->public()->published()->create(['explanation' => 'Because.']);

    $item = app(SnapshotBuilder::class)->build($question, randomizer(3));
    $snapshot = $item['snapshot'];
    $correct = collect($snapshot['choices'])->firstWhere('key', $item['correct_key']);

    expect($snapshot['stem'])->toBe($question->stem)
        ->and($snapshot['explanation'])->toBe('Because.')
        ->and($snapshot['source_visibility'])->toBe('public')
        ->and($snapshot['passage'])->toBeNull()
        ->and($snapshot['question_updated_at'])->toBe($question->updated_at->toIso8601String())
        ->and(array_column($snapshot['choices'], 'key'))->toBe(['A', 'B', 'C', 'D'])
        ->and($correct['body'])->toBe($question->correctChoice()->body);
});

it('points correct_key at the shuffled position of the right choice', function () {
    foreach (range(1, 10) as $seed) {
        $question = Question::factory()->published()->create();
        $shown = app(ChoiceShuffler::class)->shuffle($question, randomizer($seed));
        $expected = chr(ord('A') + array_search(true, array_map(fn ($c) => $c->is_correct, $shown), true));

        expect(app(SnapshotBuilder::class)->build($question, randomizer($seed))['correct_key'])->toBe($expected);
    }
});

it('snapshots the passage and media of the stem, passage and choices', function () {
    $passage = Passage::factory()->create(['body' => 'Read this.']);
    $question = Question::factory()->inPassage($passage)->published()->create(['source_id' => Source::factory()->public()]);
    $media = fn ($model, $path) => $model->media()->create([
        'disk' => 'public', 'path' => $path, 'mime' => 'image/png', 'size' => 10, 'width' => 4, 'height' => 3,
        'alt_text' => 'Alt '.$path, 'position' => 1,
    ]);
    $media($question, 'media/stem.png');
    $media($passage, 'media/passage.png');
    $media($question->choices()->first(), 'media/choice.png');

    $snapshot = app(SnapshotBuilder::class)->build($question->fresh(), randomizer())['snapshot'];
    $choiceMedia = collect($snapshot['choices'])->flatMap(fn ($choice) => $choice['media']);

    expect($snapshot['passage']['body'])->toBe('Read this.')
        ->and($snapshot['passage']['media'][0]['path'])->toBe('media/passage.png')
        ->and($snapshot['media'][0])->toMatchArray(['path' => 'media/stem.png', 'alt_text' => 'Alt media/stem.png'])
        ->and($choiceMedia->pluck('path')->all())->toBe(['media/choice.png'])
        ->and($snapshot['source_visibility'])->toBe('private'); // the passage's source is private

});
