<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Models\Question;

final readonly class DuplicateMatch
{
    /**
     * @param  float  $similarity  0..1, from similar_text on normalized stems
     */
    public function __construct(
        public Question $question,
        public float $similarity,
    ) {}
}
