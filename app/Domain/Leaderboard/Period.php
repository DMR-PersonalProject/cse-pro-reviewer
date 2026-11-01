<?php

declare(strict_types=1);

namespace App\Domain\Leaderboard;

use Carbon\CarbonImmutable;

enum Period: string
{
    case AllTime = 'all';
    case Week = 'week';

    public function label(): string
    {
        return match ($this) {
            self::AllTime => __('All time'),
            self::Week => __('This week'),
        };
    }

    /**
     * The UTC [start, end) range, or null for all time. Weeks run Monday 00:00 to Monday 00:00 in the display timezone.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function range(): ?array
    {
        if ($this === self::AllTime) {
            return null;
        }

        $start = CarbonImmutable::now(config('cse.display_timezone'))->startOfWeek(CarbonImmutable::MONDAY);

        return [$start->utc(), $start->addWeek()->utc()];
    }
}
