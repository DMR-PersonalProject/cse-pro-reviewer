<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SourceType: string implements HasLabel
{
    case Reviewer = 'reviewer';
    case Book = 'book';
    case Website = 'website';
    case Original = 'original';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Reviewer => __('Reviewer'),
            self::Book => __('Book'),
            self::Website => __('Website'),
            self::Original => __('Original'),
            self::Other => __('Other'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
