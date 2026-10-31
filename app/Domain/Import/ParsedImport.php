<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Enums\ImportFormat;

/**
 * A file read by ImportParser, not yet validated. Rows are normalized arrays (see ImportParser::row()).
 */
final readonly class ParsedImport
{
    /**
     * @param  array{name: string, type: ?string, visibility: ?string}|null  $defaultSource
     * @param  list<array<string, mixed>>  $passages
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public ImportFormat $format,
        public string $originalFilename,
        public string $name,
        public ?array $defaultSource,
        public array $passages,
        public array $rows,
    ) {}
}
