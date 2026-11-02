<?php

use App\Domain\Attempts\StartAttempt;
use App\Domain\Leaderboard\LeaderboardQuery;
use App\Domain\Leaderboard\Period;
use App\Enums\AttemptMode;
use App\Enums\AttemptTiming;
use App\Enums\QuestionStatus;
use App\Livewire\Attempt\Runner;
use App\Livewire\Leaderboard\Index;
use App\Models\Attempt;
use App\Models\AttemptResult;
use App\Models\AttemptSubtestScore;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

// M7-05 (NFR-2). Excluded from the default run: `php artisan test --compact --group=perf`.
// Times include the Livewire test harness (a full render), so they are an upper bound.

function perfMilliseconds(callable $callback): float
{
    $started = hrtime(true);
    $callback();

    return (hrtime(true) - $started) / 1e6;
}

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $source = Source::factory()->public()->create();
    $topics = Topic::query()->get();
    $now = now();

    // 5,000 published items with four choices each, bulk inserted.
    collect(range(1, 5000))->chunk(500)->each(function ($chunk) use ($topics, $source, $now) {
        Question::query()->insert($chunk->map(fn (int $i) => [
            'topic_id' => $topics[$i % $topics->count()]->id,
            'subtest_id' => $topics[$i % $topics->count()]->subtest_id,
            'source_id' => $source->id,
            'stem' => "Item {$i}?",
            'status' => QuestionStatus::Published->value,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    });
    Question::query()->select('id')->chunkById(500, function ($questions) use ($now) {
        DB::table('choices')->insert($questions->flatMap(fn (Question $question) => collect(range(1, 4))->map(fn (int $position) => [
            'question_id' => $question->id,
            'position' => $position,
            'body' => "Choice {$position}",
            'is_correct' => $position === 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]))->all());
    });

    // 200 ranked mocks, each with its four subtest scores.
    $subtests = Subtest::query()->get();
    User::factory()->withPin()->count(200)->create()->each(function (User $user, int $i) use ($subtests) {
        $attempt = Attempt::factory()->submitted()->create([
            'user_id' => $user->id, 'mode' => AttemptMode::Combined, 'subtest_id' => null,
            'item_count' => 170, 'is_standard' => true, 'time_used_seconds' => 5000 + $i,
        ]);
        AttemptResult::factory()->create(['attempt_id' => $attempt->id, 'general_rating' => 60 + ($i % 40)]);
        foreach ($subtests as $subtest) {
            AttemptSubtestScore::factory()->create(['attempt_id' => $attempt->id, 'subtest_id' => $subtest->id, 'percent' => 50 + ($i % 50)]);
        }
    });

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->attempt = app(StartAttempt::class)->mock($this->user, AttemptTiming::Timed)->attempt;
});

it('moves between items in 200 ms or less and autosaves in 300 ms or less', function () {
    $component = livewire(Runner::class, ['attempt' => $this->attempt]);
    $component->call('next'); // warm-up

    $move = perfMilliseconds(fn () => $component->call('next'));
    $save = perfMilliseconds(fn () => $component->call('answer', 3, 'B'));

    expect(Question::count())->toBe(5000)
        ->and($this->attempt->item_count)->toBe(170)
        ->and($move)->toBeLessThanOrEqual(200.0)
        ->and($save)->toBeLessThanOrEqual(300.0);
})->group('perf');

it('ranks 200 attempts in 200 ms or less', function () {
    $query = app(LeaderboardQuery::class);
    $query->mock(Period::AllTime); // warm-up

    $mock = perfMilliseconds(fn () => $query->mock(Period::AllTime));
    $subtest = perfMilliseconds(fn () => $query->subtest(Subtest::query()->first(), Period::AllTime));
    $page = perfMilliseconds(fn () => livewire(Index::class));

    expect($query->mock(Period::AllTime)->total())->toBe(200)
        ->and($mock)->toBeLessThanOrEqual(200.0)
        ->and($subtest)->toBeLessThanOrEqual(200.0)
        ->and($page)->toBeLessThanOrEqual(200.0);
})->group('perf');
