<?php

declare(strict_types=1);

namespace App\Domain\Import;

final readonly class ApprovalResult
{
    /**
     * @param  int  $skipped  requested rows that had errors or were already decided
     */
    public function __construct(
        public int $approved,
        public int $skipped,
    ) {}
}
