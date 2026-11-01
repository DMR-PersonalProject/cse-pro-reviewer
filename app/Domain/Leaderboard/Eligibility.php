<?php

declare(strict_types=1);

namespace App\Domain\Leaderboard;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which attempts may rank (FR-L2). `apply()` is the query scope for the boards; `reason()`
 * explains a single attempt. Both read the private-items setting now, so changes apply at once.
 */
final class Eligibility
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  Builder<Attempt>  $query
     * @return Builder<Attempt>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->whereIn('attempts.status', [AttemptStatus::Submitted, AttemptStatus::Timeout])
            ->where('attempts.timing', AttemptTiming::Timed)
            ->where('attempts.is_standard', true)
            ->where('attempts.is_partial', false)
            ->whereNull('attempts.hidden_at')
            ->when(! $this->includesPrivate(), fn (Builder $query) => $query->where('attempts.has_private_items', false))
            ->whereHas('user', fn (Builder $query) => $query->leaderboardEligible());
    }

    /**
     * The first rule the attempt breaks, or null when it is eligible.
     */
    public function reason(Attempt $attempt): ?IneligibleReason
    {
        /** @var User $user */
        $user = $attempt->user;

        return match (true) {
            ! in_array($attempt->status, [AttemptStatus::Submitted, AttemptStatus::Timeout], true) => IneligibleReason::NotCompleted,
            $attempt->timing === AttemptTiming::Study => IneligibleReason::Study,
            $attempt->timing === AttemptTiming::Untimed => IneligibleReason::Untimed,
            $attempt->is_partial => IneligibleReason::Partial,
            ! $attempt->is_standard => IneligibleReason::NotStandard,
            $attempt->has_private_items && ! $this->includesPrivate() => IneligibleReason::PrivateItems,
            $attempt->hidden_at !== null => IneligibleReason::Hidden,
            ! $user->is_active => IneligibleReason::Inactive,
            ! $user->isAdmin() && ! $user->hasPin() => IneligibleReason::NoPin,
            default => null,
        };
    }

    private function includesPrivate(): bool
    {
        return (bool) $this->settings->get(SettingKey::LeaderboardIncludePrivate);
    }
}
