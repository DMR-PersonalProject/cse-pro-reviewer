<?php

declare(strict_types=1);

namespace App\Domain\Scoring\Formulas;

/**
 * Formula v1 (FR-S3): correct ÷ total × 100, rounded half-up to 2 decimals. Integer maths
 * only, so 136/170 is exactly 80.00 and never 79.999...
 */
final class GeneralRatingV1
{
    public const VERSION = 'v1';

    /**
     * The rating in hundredths of a percent (8000 = 80.00).
     */
    public function hundredths(int $correct, int $total): int
    {
        if ($total === 0) {
            return 0;
        }

        // round(correct × 10000 / total) half-up: floor((2 × correct × 10000 + total) / (2 × total)).
        return intdiv(2 * $correct * 10000 + $total, 2 * $total);
    }

    public function percent(int $correct, int $total): string
    {
        return self::format($this->hundredths($correct, $total));
    }

    public static function format(int $hundredths): string
    {
        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }
}
