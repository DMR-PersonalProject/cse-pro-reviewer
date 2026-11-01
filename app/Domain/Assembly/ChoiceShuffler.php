<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\Choice;
use App\Models\Question;
use Random\Randomizer;

/**
 * Choice order for one attempt (FR-G5). The shown position decides the letter, A to E.
 */
final class ChoiceShuffler
{
    /**
     * @return list<Choice> in the order they are shown
     */
    public function shuffle(Question $question, Randomizer $randomizer): array
    {
        $choices = $question->choices->sortBy('position')->values()->all();

        return $question->lock_choice_order ? $choices : $randomizer->shuffleArray($choices);
    }
}
