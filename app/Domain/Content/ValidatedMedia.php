<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * An image that passed MediaValidator, with facts read from the file itself.
 */
final readonly class ValidatedMedia
{
    public function __construct(
        public string $path,
        public string $mime,
        public string $extension,
        public int $size,
        public int $width,
        public int $height,
        public string $altText,
    ) {}
}
