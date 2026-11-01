<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Models\Attempt;

/**
 * Either the new attempt, the attempt already in progress (resume or abandon, FR-G9),
 * or a shortfall with the number of items available (FR-G7).
 */
final readonly class StartResult
{
    public function __construct(
        public ?Attempt $attempt = null,
        public ?Attempt $active = null,
        public ?int $available = null,
        /** @var array<string, array{available: int, requested: int}> a short mock, by subtest code in order */
        public array $subtests = [],
    ) {}
}
