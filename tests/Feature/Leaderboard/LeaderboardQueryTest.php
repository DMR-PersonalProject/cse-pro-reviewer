<?php

use App\Domain\Leaderboard\LeaderboardQuery;
use App\Domain\Leaderboard\Period;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptMode;
use App\Models\Attempt;
use App\Models\AttemptResult;
use App\Models\AttemptSubtestScore;
use App\Models\Subtest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * An eligible finished mock with the given GR.
 *
 * @param  array<string, mixed>  $attributes
 */
function rankedMock(User $user, string $gr, array $attributes = []): Attempt
{
    $attempt = Attempt::factory()->submitted()->create([
        'user_id' => $user->id,
        'mode' => AttemptMode::Combined,
        'subtest_id' => null,
        'item_count' => 170,
        'is_standard' => true,
        ...$attributes,
    ]);
    AttemptResult::factory()->create(['attempt_id' => $attempt->id, 'general_rating' => $gr]);

    return $attempt;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function rankedSubtest(User $user, Subtest $subtest, string $percent, array $attributes = []): Attempt
{
    $attempt = Attempt::factory()->submitted()->create([
        'user_id' => $user->id,
        'subtest_id' => $subtest->id,
        'is_standard' => true,
        ...$attributes,
    ]);
    AttemptSubtestScore::factory()->create(['attempt_id' => $attempt->id, 'subtest_id' => $subtest->id, 'percent' => $percent]);

    return $attempt;
}

/**
 * @return list<array{ign: string, score: float}>
 */
function boardRows(mixed $page): array
{
    return collect($page->items())
        ->map(fn ($row) => ['ign' => $row->ign, 'score' => (float) $row->score])
        ->all();
}

it('AC-17: lists only the best mock per user', function () {
    $user = User::factory()->withPin()->create(['ign' => 'Ana']);
    rankedMock($user, '82.00');
    rankedMock($user, '91.50');

    expect(boardRows(app(LeaderboardQuery::class)->mock(Period::AllTime)))
        ->toBe([['ign' => 'Ana', 'score' => 91.5]]);
});

it('AC-17: equal GRs rank by less time used, then earlier submit', function () {
    $slow = User::factory()->withPin()->create(['ign' => 'Slow']);
    $fast = User::factory()->withPin()->create(['ign' => 'Fast']);
    $late = User::factory()->withPin()->create(['ign' => 'Late']);
    $top = User::factory()->withPin()->create(['ign' => 'Top']);
    rankedMock($slow, '85.00', ['time_used_seconds' => 9000]);
    rankedMock($late, '85.00', ['time_used_seconds' => 8000, 'finalized_at' => now()->subMinute()]);
    rankedMock($fast, '85.00', ['time_used_seconds' => 8000, 'finalized_at' => now()->subHour()]);
    rankedMock($top, '90.00', ['time_used_seconds' => 11000]);

    $page = app(LeaderboardQuery::class)->mock(Period::AllTime);

    expect(collect($page->items())->pluck('ign')->all())->toBe(['Top', 'Fast', 'Late', 'Slow'])
        ->and(collect($page->items())->pluck('rank')->map(fn ($rank) => (int) $rank)->all())->toBe([1, 2, 3, 4]);
});

it('AC-17: a user whose best attempt is on a later page keeps one row', function () {
    $user = User::factory()->withPin()->create(['ign' => 'Ana']);
    rankedMock($user, '70.00', ['time_used_seconds' => 100]);
    rankedMock($user, '80.00', ['time_used_seconds' => 9000]);

    $rows = app(LeaderboardQuery::class)->mock(Period::AllTime)->items();

    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]->score)->toBe(80.0)
        ->and((int) $rows[0]->time_used_seconds)->toBe(9000);
});

it('AC-18: last Sunday 23:00 Manila is not on this week\'s board on Monday', function () {
    // Monday 2026-11-02 09:00 Manila = 01:00 UTC.
    Carbon::setTestNow(Carbon::parse('2026-11-02 01:00:00', 'UTC'));
    $sunday = User::factory()->withPin()->create(['ign' => 'Sunday']);
    $monday = User::factory()->withPin()->create(['ign' => 'Monday']);
    rankedMock($sunday, '95.00', ['finalized_at' => Carbon::parse('2026-11-01 23:00:00', 'Asia/Manila')->utc()]);
    rankedMock($monday, '81.00', ['finalized_at' => Carbon::parse('2026-11-02 00:00:00', 'Asia/Manila')->utc()]);

    $query = app(LeaderboardQuery::class);

    expect(collect($query->mock(Period::Week)->items())->pluck('ign')->all())->toBe(['Monday'])
        ->and(collect($query->mock(Period::AllTime)->items())->pluck('ign')->all())->toBe(['Sunday', 'Monday']);
});

it('the weekly best is the best attempt inside the week, not the all-time best', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 04:00:00', 'UTC'));
    $user = User::factory()->withPin()->create(['ign' => 'Ana']);
    rankedMock($user, '99.00', ['finalized_at' => now()->subWeeks(2)]);
    rankedMock($user, '82.00');

    expect(boardRows(app(LeaderboardQuery::class)->mock(Period::Week)))
        ->toBe([['ign' => 'Ana', 'score' => 82.0]]);
});

it('the subtest board uses attempt_subtest_scores of individual attempts of that subtest', function () {
    $verbal = Subtest::factory()->create();
    $numerical = Subtest::factory()->create();
    $ana = User::factory()->withPin()->create(['ign' => 'Ana']);
    $ben = User::factory()->withPin()->create(['ign' => 'Ben']);
    rankedSubtest($ana, $verbal, '75.00');
    rankedSubtest($ana, $verbal, '95.00');
    rankedSubtest($ben, $numerical, '100.00');
    // A mock's verbal subtest score never counts on the verbal board.
    $mock = rankedMock($ben, '99.00');
    AttemptSubtestScore::factory()->create(['attempt_id' => $mock->id, 'subtest_id' => $verbal->id, 'percent' => '99.00']);

    $query = app(LeaderboardQuery::class);

    expect(boardRows($query->subtest($verbal, Period::AllTime)))->toBe([['ign' => 'Ana', 'score' => 95.0]])
        ->and(boardRows($query->subtest($numerical, Period::AllTime)))->toBe([['ign' => 'Ben', 'score' => 100.0]]);
});

it('AC-16a: a user without a PIN is listed only after setting one', function () {
    $user = User::factory()->create(['ign' => 'Ana']);
    rankedMock($user, '85.00');
    $query = app(LeaderboardQuery::class);

    expect($query->mock(Period::AllTime)->items())->toBeEmpty();

    $user->update(['password' => Hash::make('1234')]);
    expect(collect($query->mock(Period::AllTime)->items())->pluck('ign')->all())->toBe(['Ana']);
});

it('AC-16c: a study-mode attempt never appears, even with private sources allowed', function () {
    app(Settings::class)->set(SettingKey::LeaderboardIncludePrivate, true);
    $subtest = Subtest::factory()->create();
    $user = User::factory()->withPin()->create();
    rankedSubtest($user, $subtest, '100.00', ['timing' => 'study', 'deadline_at' => null]);

    expect(app(LeaderboardQuery::class)->subtest($subtest, Period::AllTime)->items())->toBeEmpty();
});

it('AC-16: a mock with private items appears only while the setting is on', function () {
    rankedMock(User::factory()->withPin()->create(['ign' => 'Ana']), '85.00', ['has_private_items' => true]);
    $settings = app(Settings::class);
    $query = app(LeaderboardQuery::class);

    expect($query->mock(Period::AllTime)->items())->toBeEmpty();

    $settings->set(SettingKey::LeaderboardIncludePrivate, true);
    expect($query->mock(Period::AllTime)->items())->toHaveCount(1);

    $settings->set(SettingKey::LeaderboardIncludePrivate, false);
    expect($query->mock(Period::AllTime)->items())->toBeEmpty();
});

it('an admin-hidden best attempt drops out and the next best takes its place', function () {
    $user = User::factory()->withPin()->create(['ign' => 'Ana']);
    rankedMock($user, '80.00');
    rankedMock($user, '95.00', ['hidden_at' => now(), 'hidden_reason' => 'Copied answers']);

    expect(boardRows(app(LeaderboardQuery::class)->mock(Period::AllTime)))
        ->toBe([['ign' => 'Ana', 'score' => 80.0]]);
});

it('AC-16b: a suspicious attempt is still listed', function () {
    rankedMock(User::factory()->withPin()->create(['ign' => 'Ana']), '85.00', ['is_suspicious' => true]);

    expect(app(LeaderboardQuery::class)->mock(Period::AllTime)->items())->toHaveCount(1);
});

it('AC-19: rows never carry the full name', function () {
    rankedMock(User::factory()->withPin()->create(['ign' => 'Ana', 'full_name' => 'Ana Secret Santos']), '85.00');

    $row = (array) app(LeaderboardQuery::class)->mock(Period::AllTime)->items()[0];

    expect(array_keys($row))->not->toContain('full_name')
        ->and(json_encode($row))->not->toContain('Ana Secret Santos');
});
