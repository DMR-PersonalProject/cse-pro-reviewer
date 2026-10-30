<?php

use App\Models\Subtest;
use App\Models\Topic;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Database\UniqueConstraintViolationException;

const EXPECTED_TAXONOMY = [
    'verbal' => ['word_meaning', 'sentence_completion', 'error_recognition', 'sentence_structure', 'paragraph_organization', 'reading_comprehension'],
    'numerical' => ['basic_operations', 'number_sequences', 'word_problems'],
    'analytical' => ['word_analogy', 'abstract_reasoning', 'assumptions_conclusions', 'data_interpretation'],
    'general_info' => ['philippine_constitution', 'ra_6713', 'peace_human_rights', 'environment'],
];

it('seeds 4 subtests and 17 topics with fixed codes in order', function () {
    $this->seed(TaxonomySeeder::class);

    $taxonomy = Subtest::with('topics')->orderBy('position')->get()
        ->mapWithKeys(fn (Subtest $subtest) => [$subtest->code => $subtest->topics->pluck('code')->all()])
        ->all();

    expect($taxonomy)->toBe(EXPECTED_TAXONOMY)
        ->and(Subtest::count())->toBe(4)
        ->and(Topic::count())->toBe(17);
});

it('names every subtest and topic', function () {
    $this->seed(TaxonomySeeder::class);

    expect(Subtest::where('code', 'general_info')->value('name'))->toBe('General Information')
        ->and(Topic::where('code', 'ra_6713')->value('name'))->toBe('R.A. 6713 (Code of Conduct and Ethical Standards)')
        ->and(Topic::whereNull('name')->orWhere('name', '')->count())->toBe(0);
});

it('is idempotent', function () {
    $this->seed(TaxonomySeeder::class);
    $ids = Topic::pluck('id', 'code')->all();

    $this->seed(TaxonomySeeder::class);

    expect(Subtest::count())->toBe(4)
        ->and(Topic::count())->toBe(17)
        ->and(Topic::pluck('id', 'code')->all())->toBe($ids);
});

it('restores a renamed topic and keeps its id', function () {
    $this->seed(TaxonomySeeder::class);
    $topic = Topic::where('code', 'word_problems')->sole();
    $topic->update(['name' => 'Changed', 'position' => 9]);

    $this->seed(TaxonomySeeder::class);

    expect($topic->fresh())->name->toBe('Word problems')->position->toBe(3);
});

it('keeps subtest codes unique', function () {
    Subtest::create(['code' => 'verbal', 'name' => 'Verbal', 'position' => 1]);
    Subtest::create(['code' => 'verbal', 'name' => 'Again', 'position' => 2]);
})->throws(UniqueConstraintViolationException::class);

it('keeps topic codes unique within a subtest', function () {
    $subtest = Subtest::create(['code' => 'verbal', 'name' => 'Verbal', 'position' => 1]);
    $subtest->topics()->create(['code' => 'x', 'name' => 'X', 'position' => 1]);
    $subtest->topics()->create(['code' => 'x', 'name' => 'X again', 'position' => 2]);
})->throws(UniqueConstraintViolationException::class);

it('runs from the database seeder', function () {
    $this->seed();

    expect(Topic::count())->toBe(17);
});
