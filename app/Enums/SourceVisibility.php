<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * FR-Q4: items from private sources are admin-only and never count toward the leaderboard.
 */
enum SourceVisibility: string implements HasLabel
{
    case Private = 'private';
    case Public = 'public';

    public static function default(): self
    {
        return self::Private;
    }

    public function label(): string
    {
        return match ($this) {
            self::Private => __('Private'),
            self::Public => __('Public'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
