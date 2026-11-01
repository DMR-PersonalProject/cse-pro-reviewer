<?php

use App\Domain\Attempts\StartAttempt;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;

/**
 * A small split (4 per subtest) and `$counts` published items per subtest code, all in its first topic.
 *
 * @param  array<string, int>  $counts
 */
function smallMockBank(array $counts): void
{
    $settings = app(Settings::class);

    foreach (SettingKey::itemSplit() as $key) {
        $settings->set($key, 4);
    }

    $source = Source::factory()->public()->create();

    foreach ($counts as $code => $count) {
        $topic = Subtest::query()->where('code', $code)->first()->topics()->first();
        Question::factory()->published()->count($count)->create(['topic_id' => $topic->id, 'source_id' => $source->id]);
    }
}

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->user = User::factory()->create();
});

it('FR-G7: reports what each subtest has when the bank is short and starts nothing', function () {
    smallMockBank(['verbal' => 4, 'numerical' => 2, 'analytical' => 4, 'general_info' => 0]);

    $result = app(StartAttempt::class)->mock($this->user, AttemptTiming::Timed);

    expect($result->attempt)->toBeNull()
        ->and($result->available)->toBe(10)
        ->and($result->subtests)->toBe([
            'verbal' => ['available' => 4, 'requested' => 4],
            'numerical' => ['available' => 2, 'requested' => 4],
            'analytical' => ['available' => 4, 'requested' => 4],
            'general_info' => ['available' => 0, 'requested' => 4],
        ])
        ->and(Attempt::count())->toBe(0);
});

it('starts a partial mock when the user accepts, never standard', function () {
    smallMockBank(['verbal' => 4, 'numerical' => 2, 'analytical' => 4, 'general_info' => 0]);

    $attempt = app(StartAttempt::class)->mock($this->user, AttemptTiming::Timed, allowShortfall: true)->attempt;

    expect($attempt->is_partial)->toBeTrue()
        ->and($attempt->is_standard)->toBeFalse()
        ->and($attempt->item_count)->toBe(10)
        ->and($attempt->answers)->toHaveCount(10)
        // ⌈190 × 10 ÷ 16⌉ = 119 minutes for the items actually given.
        ->and((int) round($attempt->started_at->diffInMinutes($attempt->deadline_at)))->toBe(119);
});

it('never starts a mock with no items', function () {
    smallMockBank([]);

    $result = app(StartAttempt::class)->mock($this->user, AttemptTiming::Timed, allowShortfall: true);

    expect($result->attempt)->toBeNull()
        ->and($result->available)->toBe(0)
        ->and(Attempt::count())->toBe(0);
});

it('starts a full timed mock as standard with one 190-minute clock', function () {
    smallMockBank(['verbal' => 4, 'numerical' => 4, 'analytical' => 4, 'general_info' => 4]);
    app(Settings::class)->set(SettingKey::TotalMinutes, 190);

    $attempt = app(StartAttempt::class)->mock($this->user, AttemptTiming::Timed)->attempt;

    expect($attempt->is_partial)->toBeFalse()
        ->and($attempt->is_standard)->toBeTrue()
        ->and($attempt->subtest_id)->toBeNull()
        ->and((int) round($attempt->started_at->diffInMinutes($attempt->deadline_at)))->toBe(190);
});
