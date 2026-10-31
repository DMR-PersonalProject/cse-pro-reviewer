<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * A standalone question, or a passage's published questions in authored order (FR-G4).
 */
final readonly class SelectionUnit
{
    /**
     * @param  Collection<int, Question>  $questions
     * @param  int|null  $lastSeen  Unix timestamp; null when no item of the unit was seen
     */
    public function __construct(
        public int $topicId,
        public Collection $questions,
        public ?int $lastSeen,
    ) {}

    public function size(): int
    {
        return $this->questions->count();
    }
}
