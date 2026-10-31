<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Audit\Audit;
use App\Enums\SourceVisibility;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Validates and flags a parsed file and stores it as a pending batch (FR-M2, FR-M3). Nothing is
 * written to the question bank here; that happens row by row in ApproveImportRows.
 */
class CreateImportBatch
{
    public function __construct(
        private readonly RowValidator $validator,
        private readonly RowFlagger $flagger,
    ) {}

    /**
     * @param  ?string  $mediaRoot  the staged folder media is read from; null for an admin upload
     */
    public function handle(ParsedImport $import, ?User $user, ?string $mediaRoot): ImportBatch
    {
        $errors = $this->validator->validate($import, $mediaRoot);
        $flags = $this->flagger->flag($import, $mediaRoot);
        $warnings = $this->sourceWarnings($import->defaultSource);

        $passages = [];

        foreach ($import->passages as $passage) {
            $passages[$passage['ref']] ??= $passage;
        }

        return DB::transaction(function () use ($import, $user, $mediaRoot, $errors, $flags, $warnings, $passages) {
            $batch = ImportBatch::create([
                'user_id' => $user?->id,
                'name' => $import->name,
                'original_filename' => $import->originalFilename,
                'format' => $import->format,
                'meta' => [
                    'default_source' => $import->defaultSource,
                    'passages' => $passages,
                    'warnings' => $warnings,
                    'media_root' => $mediaRoot,
                ],
                'total_rows' => count($import->rows),
                'valid_rows' => count(array_filter($errors, fn (array $rowErrors) => $rowErrors === [])),
            ]);

            foreach ($import->rows as $index => $row) {
                $batch->rows()->create([
                    'row_number' => $row['row_number'],
                    'payload' => $row,
                    'errors' => $errors[$index],
                    'flags' => $flags[$index]['flags'],
                    'duplicate_of_question_id' => $flags[$index]['duplicate_of_question_id'],
                ]);
            }

            if (($import->defaultSource['visibility'] ?? null) === SourceVisibility::Public->value) {
                Audit::record('import.public_source_requested', $batch, meta: ['source' => $import->defaultSource['name']]);
            }

            return $batch->load('rows');
        });
    }

    /**
     * What will happen to the file's source (FR-M6): new sources are always private, and an
     * existing source with the same name is reused unchanged.
     *
     * @param  array{name: string, type: ?string, visibility: ?string}|null  $source
     * @return list<string>
     */
    private function sourceWarnings(?array $source): array
    {
        if ($source === null) {
            return [];
        }

        $existing = Source::where('name', $source['name'])->first();

        if ($existing) {
            return [__('The existing source “:name” (:visibility) will be used as it is.', [
                'name' => $existing->name,
                'visibility' => $existing->visibility->label(),
            ])];
        }

        if ($source['visibility'] === SourceVisibility::Public->value) {
            return [__('The file asked for a public source; “:name” will be created as private.', ['name' => $source['name']])];
        }

        return [];
    }
}
