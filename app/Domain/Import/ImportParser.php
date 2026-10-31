<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Import\Exceptions\ImportFileException;
use App\Enums\ImportFormat;
use JsonException;

/**
 * Reads a JSON (schema v1) or CSV import file into normalized rows (plan §8.2). It only reshapes
 * and trims: nothing is checked against the database and a missing answer stays null.
 */
class ImportParser
{
    public const SCHEMA_VERSION = 1;

    public const CSV_HEADER = [
        'subtest', 'topic', 'language', 'stem', 'choice_a', 'choice_b', 'choice_c', 'choice_d', 'choice_e',
        'answer', 'explanation', 'difficulty', 'tags', 'source_file', 'source_page', 'notes', 'confidence',
    ];

    public const CSV_HEADER_LINE = 'subtest,topic,language,stem,choice_a,choice_b,choice_c,choice_d,choice_e,'
        .'answer,explanation,difficulty,tags,source_file,source_page,notes,confidence';

    /**
     * @throws ImportFileException
     */
    public function parseFile(string $path, ?string $originalFilename = null): ParsedImport
    {
        $originalFilename ??= basename($path);

        $format = match (mb_strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION))) {
            'json' => ImportFormat::Json,
            'csv' => ImportFormat::Csv,
            default => throw new ImportFileException(__('Import a JSON or CSV file.')),
        };

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new ImportFileException(__('The file :file can’t be read.', ['file' => $originalFilename]));
        }

        return $this->parse($contents, $format, $originalFilename);
    }

    /**
     * @throws ImportFileException
     */
    public function parse(string $contents, ImportFormat $format, string $originalFilename): ParsedImport
    {
        $contents = $this->stripBom($contents);

        return $format === ImportFormat::Json
            ? $this->parseJson($contents, $originalFilename)
            : $this->parseCsv($contents, $originalFilename);
    }

    private function parseJson(string $contents, string $originalFilename): ParsedImport
    {
        try {
            $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ImportFileException(__('The file is not valid JSON: :error', ['error' => $e->getMessage()]));
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new ImportFileException(__('The JSON file must be an object with schema_version and items.'));
        }

        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new ImportFileException(__('Unsupported schema_version. Use :version.', ['version' => self::SCHEMA_VERSION]));
        }

        $items = $data['items'] ?? null;

        if (! is_array($items) || $items === []) {
            throw new ImportFileException(__('The file has no items.'));
        }

        $batch = is_array($data['batch'] ?? null) ? $data['batch'] : [];
        $rows = [];

        foreach (array_values($items) as $index => $item) {
            $rows[] = $this->row($index + 1, is_array($item) ? $item : []);
        }

        $passages = [];

        foreach (is_array($data['passages'] ?? null) ? array_values($data['passages']) : [] as $passage) {
            $passages[] = $this->passage(is_array($passage) ? $passage : []);
        }

        return new ParsedImport(
            format: ImportFormat::Json,
            originalFilename: $originalFilename,
            name: $this->string($batch['name'] ?? null) ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            defaultSource: $this->source($batch['default_source'] ?? null),
            passages: $passages,
            rows: $rows,
        );
    }

    private function parseCsv(string $contents, string $originalFilename): ParsedImport
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $header = fgetcsv($stream, escape: '');
        $header = is_array($header) ? array_map(fn ($column) => mb_strtolower(trim((string) $column)), $header) : [];

        if ($header !== self::CSV_HEADER) {
            fclose($stream);

            throw new ImportFileException(__('The CSV header must be exactly: :header', ['header' => self::CSV_HEADER_LINE]));
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $line++;

            if ($values === [null] || implode('', array_map('trim', array_map('strval', $values))) === '') {
                continue;
            }

            $values = array_pad(array_slice($values, 0, count(self::CSV_HEADER)), count(self::CSV_HEADER), '');
            $rows[] = $this->csvRow($line, array_combine(self::CSV_HEADER, $values));
        }

        fclose($stream);

        if ($rows === []) {
            throw new ImportFileException(__('The file has no items.'));
        }

        return new ParsedImport(
            format: ImportFormat::Csv,
            originalFilename: $originalFilename,
            name: pathinfo($originalFilename, PATHINFO_FILENAME),
            defaultSource: null,
            passages: [],
            rows: $rows,
        );
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function csvRow(int $line, array $values): array
    {
        $choices = array_map(fn ($key) => $values[$key], ['choice_a', 'choice_b', 'choice_c', 'choice_d', 'choice_e']);

        // Unused trailing choice columns are empty; a gap before the last choice is kept as an
        // empty choice so the validator reports it.
        while ($choices !== [] && trim((string) end($choices)) === '') {
            array_pop($choices);
        }

        return $this->row($line, [
            'subtest' => $values['subtest'],
            'topic' => $values['topic'],
            'language' => $values['language'],
            'stem' => $values['stem'],
            'choices' => $choices,
            'answer' => $values['answer'],
            'explanation' => $values['explanation'],
            'difficulty' => $values['difficulty'],
            'tags' => explode('|', $values['tags']),
            'provenance' => ['file' => $values['source_file'], 'page' => $values['source_page'], 'notes' => $values['notes']],
            'extraction' => ['confidence' => $values['confidence']],
        ]);
    }

    /**
     * The normalized shape every row has, whatever the format.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function row(int $rowNumber, array $item): array
    {
        $provenance = is_array($item['provenance'] ?? null) ? $item['provenance'] : [];
        $extraction = is_array($item['extraction'] ?? null) ? $item['extraction'] : [];
        $answer = $this->string($item['answer'] ?? null);

        return [
            'row_number' => $rowNumber,
            'subtest' => $this->code($item['subtest'] ?? null),
            'topic' => $this->code($item['topic'] ?? null),
            'passage_ref' => $this->string($item['passage_ref'] ?? null),
            'language' => $this->code($item['language'] ?? null) ?: 'en',
            'stem' => $this->string($item['stem'] ?? null) ?? '',
            'choices' => is_array($item['choices'] ?? null)
                ? array_map(fn ($choice) => $this->string($choice) ?? '', array_values($item['choices']))
                : [],
            'answer' => $answer === null ? null : mb_strtoupper($answer),
            'explanation' => $this->string($item['explanation'] ?? null),
            'difficulty' => $this->code($item['difficulty'] ?? null) ?: null,
            'tags' => $this->tags($item['tags'] ?? []),
            'lock_choice_order' => (bool) ($item['lock_choice_order'] ?? false),
            'media' => $this->media($item['media'] ?? []),
            'provenance' => [
                'file' => $this->string($provenance['file'] ?? null),
                'page' => $this->string($provenance['page'] ?? null),
                'notes' => $this->string($provenance['notes'] ?? null),
            ],
            'extraction' => [
                'confidence' => is_numeric($extraction['confidence'] ?? null) ? (float) $extraction['confidence'] : null,
                'needs_review' => (bool) ($extraction['needs_review'] ?? false),
                'notes' => $this->string($extraction['notes'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $passage
     * @return array<string, mixed>
     */
    private function passage(array $passage): array
    {
        return [
            'ref' => $this->string($passage['ref'] ?? null) ?? '',
            'subtest' => $this->code($passage['subtest'] ?? null),
            'topic' => $this->code($passage['topic'] ?? null),
            'language' => $this->code($passage['language'] ?? null) ?: 'en',
            'title' => $this->string($passage['title'] ?? null),
            'body' => $this->string($passage['body'] ?? null),
            'media' => $this->media($passage['media'] ?? []),
        ];
    }

    /**
     * @return array{name: string, type: ?string, visibility: ?string}|null
     */
    private function source(mixed $source): ?array
    {
        $name = is_array($source) ? $this->string($source['name'] ?? null) : null;

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'type' => $this->code($source['type'] ?? null) ?: null,
            'visibility' => $this->code($source['visibility'] ?? null) ?: null,
        ];
    }

    /**
     * @return list<array{path: string, alt: ?string}>
     */
    private function media(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $items = [];

        foreach (array_values($media) as $item) {
            $item = is_array($item) ? $item : ['path' => $item];
            $items[] = [
                'path' => $this->string($item['path'] ?? null) ?? '',
                'alt' => $this->string($item['alt'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $names = array_filter(array_map(fn ($tag) => $this->string($tag), $tags));

        return array_values(array_unique($names));
    }

    /**
     * Trimmed text, or null when empty or not a scalar.
     */
    private function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function code(mixed $value): string
    {
        return mb_strtolower($this->string($value) ?? '');
    }

    private function stripBom(string $contents): string
    {
        return str_starts_with($contents, "\u{FEFF}") ? substr($contents, 3) : $contents;
    }
}
