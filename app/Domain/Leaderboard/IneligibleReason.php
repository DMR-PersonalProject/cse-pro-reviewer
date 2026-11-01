<?php

declare(strict_types=1);

namespace App\Domain\Leaderboard;

/**
 * Why an attempt is not on the leaderboard (FR-L2), shown on the results page.
 */
enum IneligibleReason: string
{
    case NotCompleted = 'not_completed';
    case Study = 'study';
    case Untimed = 'untimed';
    case Partial = 'partial';
    case NotStandard = 'not_standard';
    case PrivateItems = 'private_items';
    case Hidden = 'hidden';
    case Inactive = 'inactive';
    case NoPin = 'no_pin';

    public function label(): string
    {
        return match ($this) {
            self::NotCompleted => __('Not on leaderboard: the attempt was not completed'),
            self::Study => __('Not on leaderboard: study mode'),
            self::Untimed => __('Not on leaderboard: untimed'),
            self::Partial => __('Not on leaderboard: partial test'),
            self::NotStandard => __('Not on leaderboard: not the standard length or has a topic filter'),
            self::PrivateItems => __('Not on leaderboard: includes private items'),
            self::Hidden => __('Not on leaderboard: hidden by an admin'),
            self::Inactive => __('Not on leaderboard: account inactive'),
            self::NoPin => __('Not on leaderboard: set a PIN to be listed'),
        };
    }
}
