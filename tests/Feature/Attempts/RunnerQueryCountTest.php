<?php

use App\Domain\Attempts\StartAttempt;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptTiming;
use App\Livewire\Attempt\Runner;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

// M7-05: no N+1 on the runner. Queries per render and per move must not grow with the item count.

/**
 * Queries for the first render and for one "next" on a fresh mock with `$perSubtest` items per subtest.
 *
 * @return array{render: int, next: int}
 */
function runnerQueries(int $perSubtest): array
{
    foreach (SettingKey::itemSplit() as $key) {
        app(Settings::class)->set($key, $perSubtest);
    }

    $user = User::factory()->create();
    $attempt = app(StartAttempt::class)->mock($user, AttemptTiming::Timed)->attempt;
    test()->actingAs($user);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $component = livewire(Runner::class, ['attempt' => $attempt]);
    $render = count(DB::getQueryLog());

    DB::flushQueryLog();
    $component->call('next');
    $next = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['render' => $render, 'next' => $next];
}

it('keeps the runner query count flat as the attempt grows', function () {
    $this->seed(TaxonomySeeder::class);
    $source = Source::factory()->public()->create();

    foreach (Subtest::query()->with('topics')->get() as $subtest) {
        Question::factory()->published()->count(12)->create(['topic_id' => $subtest->topics->first()->id, 'source_id' => $source->id]);
    }

    $small = runnerQueries(2);
    $large = runnerQueries(12);

    expect($large['render'])->toBe($small['render'])
        ->and($large['next'])->toBe($small['next']);
});
