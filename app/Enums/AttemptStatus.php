<?php

namespace App\Enums;

/**
 * The final statuses double as the result's end reason (FR-S4: submitted|timeout|abandoned).
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Timeout = 'timeout';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('In progress'),
            self::Submitted => __('Submitted'),
            self::Timeout => __('Time ran out'),
            self::Abandoned => __('Abandoned'),
        };
    }
}
