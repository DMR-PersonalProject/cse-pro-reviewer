<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImportFormat: string implements HasLabel
{
    case Json = 'json';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Json => __('JSON'),
            self::Csv => __('CSV'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
