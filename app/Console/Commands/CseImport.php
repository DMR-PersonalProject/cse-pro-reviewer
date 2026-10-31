<?php

namespace App\Console\Commands;

use App\Domain\Import\ApproveImportRows;
use App\Domain\Import\CreateImportBatch;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\ImportParser;
use App\Domain\Import\RowFlagger;
use App\Domain\Import\RowValidator;
use App\Models\ImportRow;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Imports staged JSON or CSV through the same validator and batch as the admin wizard (FR-M3).
 * Media paths are read from the staged folder (config cse.staged_path). Nothing is ever published.
 */
class CseImport extends Command
{
    protected $signature = 'cse:import
        {path : JSON or CSV file, absolute or relative to the project}
        {--dry-run : Validate and show the summary without saving anything}
        {--approve-valid : Also approve every row without errors (as draft or needs_review)}';

    protected $description = 'Import staged questions into a pending import batch';

    public function handle(
        ImportParser $parser,
        RowValidator $validator,
        RowFlagger $flagger,
        CreateImportBatch $createBatch,
        ApproveImportRows $approve,
    ): int {
        $path = $this->argument('path');
        $path = preg_match('#^([A-Za-z]:)?[/\\\\]#', $path) ? $path : base_path($path);
        $mediaRoot = config('cse.staged_path');

        try {
            $import = $parser->parseFile($path);
        } catch (ImportFileException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $errors = $validator->validate($import, $mediaRoot);
            $flags = $flagger->flag($import, $mediaRoot);

            $this->summary(array_map(fn (array $row, array $rowErrors, array $rowFlags) => [
                $row['row_number'], $row['stem'], $rowErrors, $rowFlags['flags'],
            ], $import->rows, $errors, $flags));
            $this->warnings($createBatch->sourceWarnings($import->defaultSource));
            $this->components->info(__('Dry run: nothing was saved.'));

            return self::SUCCESS;
        }

        $batch = $createBatch->handle($import, null, $mediaRoot);

        $this->summary($batch->rows->map(fn (ImportRow $row) => [
            $row->row_number, $row->payload['stem'], $row->errors, $row->flags,
        ])->all());
        $this->warnings($batch->meta['warnings']);
        $this->components->info(__('Batch #:id created with :total rows (:valid valid). Review it in /admin.', [
            'id' => $batch->id, 'total' => $batch->total_rows, 'valid' => $batch->valid_rows,
        ]));

        if ($this->option('approve-valid')) {
            $result = $approve->handle($batch, $batch->rows->reject->hasErrors()->modelKeys());
            $this->components->info(__('Approved :count rows.', ['count' => $result->approved]));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{int, string, list<string>, list<array{code: string, detail: ?string}>}>  $rows
     */
    private function summary(array $rows): void
    {
        $this->table(
            [__('Row'), __('Stem'), __('Errors'), __('Flags')],
            array_map(fn (array $row) => [
                $row[0],
                Str::limit($row[1], 50),
                $row[2] === [] ? '-' : implode("\n", $row[2]),
                $row[3] === [] ? '-' : implode("\n", array_column($row[3], 'code')),
            ], $rows),
        );
    }

    /**
     * @param  list<string>  $warnings
     */
    private function warnings(array $warnings): void
    {
        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }
    }
}
