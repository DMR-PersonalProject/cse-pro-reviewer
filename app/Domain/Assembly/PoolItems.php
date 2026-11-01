<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Fresh items are drawn first; seen items follow, least recently seen first.
 */
final readonly class PoolItems
{
    /**
     * @param  Collection<int, Question>  $fresh  ordered by id
     * @param  Collection<int, Question>  $seen  oldest first
     * @param  array<int, int>  $lastSeen  question id => Unix timestamp
     */
    public function __construct(
        public Collection $fresh,
        public Collection $seen,
        public array $lastSeen,
    ) {}

    /**
     * @return Collection<int, Question>
     */
    public function all(): Collection
    {
        return $this->fresh->concat($this->seen);
    }
}
