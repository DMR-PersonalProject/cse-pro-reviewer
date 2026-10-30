<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * The comparable form of a stem (FR-Q6): lowercase, punctuation replaced by spaces, whitespace
 * collapsed. Math symbols such as + and = are not punctuation and are kept; hyphens and dashes are.
 */
class StemNormalizer
{
    public function normalize(?string $stem): string
    {
        $text = mb_strtolower((string) $stem);
        $text = (string) preg_replace('/\p{P}+/u', ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
