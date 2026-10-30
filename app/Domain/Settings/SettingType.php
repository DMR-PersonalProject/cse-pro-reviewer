<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum SettingType
{
    case Integer;
    case Decimal;
    case Boolean;

    public function cast(mixed $value): int|float|bool
    {
        return match ($this) {
            self::Integer => (int) $value,
            self::Decimal => (float) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        };
    }
}
