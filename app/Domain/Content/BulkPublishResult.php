<?php

declare(strict_types=1);

namespace App\Domain\Content;

final readonly class BulkPublishResult
{
    /**
     * @param  list<int>  $published  question ids
     * @param  array<int, list<string>>  $skipped  question id => blockers
     */
    public function __construct(
        public array $published,
        public array $skipped,
    ) {}

    public function total(): int
    {
        return count($this->published) + count($this->skipped);
    }
}
