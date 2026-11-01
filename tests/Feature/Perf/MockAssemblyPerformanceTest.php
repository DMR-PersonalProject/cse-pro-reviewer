<?php

use App\Domain\Assembly\TestAssembler;
use App\Domain\Settings\Settings;
use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;

// NFR-2. Excluded from the default run: `php artisan test --compact --group=perf`.
it('assembles a 170-item mock from a 5,000-item bank in 2 s or less', function () {
    $this->seed(TaxonomySeeder::class);
    $source = Source::factory()->public()->create();
    $topics = Topic::query()->get();
    $now = now();

    // Bulk insert: the assembler reads question rows only, so choices are not needed here.
    $rows = collect(range(1, 5000))->map(function (int $i) use ($topics, $source, $now) {
        $topic = $topics[$i % $topics->count()];

        return [
            'topic_id' => $topic->id,
            'subtest_id' => $topic->subtest_id,
            'source_id' => $source->id,
            'stem' => "Item {$i}?",
            'status' => QuestionStatus::Published->value,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    });
    $rows->chunk(500)->each(fn ($chunk) => Question::query()->insert($chunk->values()->all()));

    $user = User::factory()->create();
    $split = app(Settings::class)->itemSplit();

    $started = hrtime(true);
    $result = app(TestAssembler::class)->assembleMock($user, $split, 42);
    $seconds = (hrtime(true) - $started) / 1e9;

    expect(Question::count())->toBe(5000)
        ->and($result->count())->toBe(170)
        ->and($seconds)->toBeLessThanOrEqual(2.0);
})->group('perf');
