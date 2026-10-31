<?php

namespace App\Enums;

/**
 * Individual is one subtest; combined is the full mock exam.
 */
enum AttemptMode: string
{
    case Individual = 'individual';
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('Individual test'),
            self::Combined => __('Mock exam'),
        };
    }
}
