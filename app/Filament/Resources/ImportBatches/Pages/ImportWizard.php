<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Domain\Import\CreateImportBatch;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\ImportParser;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Step 1 of an import: upload a JSON or CSV file. Images can't come along here, so rows that
 * reference media are flagged "attach in editor" (plan §8.2). Step 2 is the batch preview.
 *
 * @property-read Schema $form
 */
class ImportWizard extends Page
{
    protected static string $resource = ImportBatchResource::class;

    /** Upload limit in KB. */
    public const MAX_KB = 5 * 1024;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return ImportBatchResource::canCreate();
    }

    public function getTitle(): string
    {
        return __('Upload questions');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Text::make(__('Rows are checked and shown for review first. Nothing is added to the question bank until you approve rows, and nothing is ever published by an import.')),
                FileUpload::make('file')
                    ->label(__('JSON or CSV file'))
                    ->helperText(__('Format: content/README.md. Images in a JSON file are not uploaded here; attach them in the editor after approval.'))
                    ->storeFiles(false)
                    ->maxSize(self::MAX_KB)
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('checkFile')
                ->footer([
                    Actions::make([
                        Action::make('checkFile')->label(__('Check file'))->submit('checkFile'),
                    ]),
                ]),
        ]);
    }

    public function checkFile(ImportParser $parser, CreateImportBatch $createBatch): void
    {
        $file = $this->form->getState()['file'];
        $file = is_array($file) ? reset($file) : $file;

        if (! $file instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['data.file' => __('Upload a JSON or CSV file.')]);
        }

        try {
            $import = $parser->parseFile($file->getRealPath(), $file->getClientOriginalName());
        } catch (ImportFileException $e) {
            throw ValidationException::withMessages(['data.file' => $e->getMessage()]);
        }

        $batch = $createBatch->handle($import, Auth::user(), null);

        $this->redirect(ImportBatchResource::getUrl('view', ['record' => $batch]));
    }
}
