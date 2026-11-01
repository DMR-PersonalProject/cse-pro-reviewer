<?php

namespace App\Enums;

/**
 * Only timed attempts have a deadline. Study reveals each answer right after it is chosen.
 */
enum AttemptTiming: string
{
    case Timed = 'timed';
    case Untimed = 'untimed';
    case Study = 'study';

    public function label(): string
    {
        return match ($this) {
            self::Timed => __('Timed'),
            self::Untimed => __('Untimed'),
            self::Study => __('Study'),
        };
    }
}
