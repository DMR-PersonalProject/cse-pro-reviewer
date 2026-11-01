<?php

use App\Domain\Leaderboard\Eligibility;
use App\Domain\Leaderboard\IneligibleReason;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * A finished, timed, standard, public attempt by a user with a PIN: eligible unless overridden.
 *
 * @param  array<string, mixed>  $attributes
 */
function eligibleAttempt(array $attributes = [], ?User $user = null): Attempt
{
    return Attempt::factory()->submitted()->create([
        'user_id' => ($user ?? User::factory()->withPin()->create())->id,
        'is_standard' => true,
        ...$attributes,
    ]);
}

/**
 * The scope and the reason resolver must agree on every attempt.
 */
function expectIneligible(Attempt $attempt, IneligibleReason $reason): void
{
    $eligibility = app(Eligibility::class);

    expect($eligibility->reason($attempt->fresh()))->toBe($reason)
        ->and($eligibility->apply(Attempt::query())->pluck('id')->all())->not->toContain($attempt->id);
}

function expectEligible(Attempt $attempt): void
{
    $eligibility = app(Eligibility::class);

    expect($eligibility->reason($attempt->fresh()))->toBeNull()
        ->and($eligibility->apply(Attempt::query())->pluck('id')->all())->toContain($attempt->id);
}

it('accepts a finished, timed, standard, public attempt by a user with a PIN', function () {
    expectEligible(eligibleAttempt());
    expectEligible(eligibleAttempt(['status' => AttemptStatus::Timeout]));
});

it('accepts an admin through their password', function () {
    expectEligible(eligibleAttempt(user: User::factory()->admin()->create()));
});

it('AC-16: private items count only while the setting is on, read at query time', function () {
    $attempt = eligibleAttempt(['has_private_items' => true]);
    $settings = app(Settings::class);

    expectIneligible($attempt, IneligibleReason::PrivateItems);

    $settings->set(SettingKey::LeaderboardIncludePrivate, true);
    expectEligible($attempt);

    $settings->set(SettingKey::LeaderboardIncludePrivate, false);
    expectIneligible($attempt, IneligibleReason::PrivateItems);
});

it('AC-16a: a user without a PIN is not eligible until they set one', function () {
    $user = User::factory()->create();
    $attempt = eligibleAttempt(user: $user);

    expectIneligible($attempt, IneligibleReason::NoPin);

    $user->update(['password' => Hash::make('1234')]);
    expectEligible($attempt);
});

it('AC-16c: a study-mode attempt is never eligible, even with private items allowed', function () {
    app(Settings::class)->set(SettingKey::LeaderboardIncludePrivate, true);

    // is_standard stays on to prove the timing check stands on its own.
    expectIneligible(eligibleAttempt(['timing' => 'study', 'deadline_at' => null]), IneligibleReason::Study);
});

it('never accepts an untimed attempt', function () {
    $attempt = eligibleAttempt(['timing' => 'untimed', 'deadline_at' => null]);

    expectIneligible($attempt, IneligibleReason::Untimed);
});

it('never accepts a partial attempt', function () {
    expectIneligible(eligibleAttempt(['is_partial' => true, 'is_standard' => false]), IneligibleReason::Partial);
});

it('never accepts a non-standard length', function () {
    expectIneligible(eligibleAttempt(['is_standard' => false]), IneligibleReason::NotStandard);
});

it('never accepts a hidden attempt', function () {
    expectIneligible(eligibleAttempt(['hidden_at' => now(), 'hidden_reason' => 'Test']), IneligibleReason::Hidden);
});

it('never accepts an attempt by an inactive user', function () {
    expectIneligible(eligibleAttempt(user: User::factory()->withPin()->inactive()->create()), IneligibleReason::Inactive);
});

it('never accepts an attempt that is still open or was abandoned', function () {
    expectIneligible(eligibleAttempt(['status' => AttemptStatus::InProgress, 'finalized_at' => null]), IneligibleReason::NotCompleted);
    expectIneligible(eligibleAttempt(['status' => AttemptStatus::Abandoned]), IneligibleReason::NotCompleted);
});

it('keeps a suspicious attempt eligible (FR-L4)', function () {
    expectEligible(eligibleAttempt(['is_suspicious' => true]));
});
