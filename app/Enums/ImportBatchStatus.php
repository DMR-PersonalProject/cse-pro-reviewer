<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A batch is pending while any row still waits for a decision.
 */
enum ImportBatchStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Completed => __('Completed'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
