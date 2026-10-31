<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImportRowDecision: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
