<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The language of a question's content, not of the UI.
 */
enum Language: string implements HasLabel
{
    case En = 'en';
    case Fil = 'fil';

    public function label(): string
    {
        return match ($this) {
            self::En => __('English'),
            self::Fil => __('Filipino'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
