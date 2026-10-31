<?php

use App\Enums\ImportFormat;
use App\Enums\ImportRowDecision;
use App\Enums\QuestionStatus;
use App\Enums\SourceVisibility;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Resources\ImportBatches\Pages\ImportWizard;
use App\Filament\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Resources\ImportBatches\RelationManagers\RowsRelationManager;
use App\Models\ImportBatch;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function uploadCsv(string ...$lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent('upload.csv', implode("\n", [
        'subtest,topic,language,stem,choice_a,choice_b,choice_c,choice_d,choice_e,answer,explanation,difficulty,tags,source_file,source_page,notes,confidence',
        ...$lines,
    ]));
}

function uploadedBatch(object $test): ImportBatch
{
    $test->actingAs($test->admin);

    livewire(ImportWizard::class)
        ->fillForm(['file' => uploadCsv(
            'numerical,basic_operations,en,What is 15% of 240?,24,30,36,40,,C,Ten plus five percent.,easy,percent,,,,1.0',
            'numerical,basic_operations,en,Only one choice here?,24,,,,,A,,,,,,,',
            'verbal,word_meaning,en,Closest in meaning to METICULOUS?,Careful,Careless,,,,,,,,,,,',
        )])
        ->call('upload')
        ->assertHasNoFormErrors();

    return ImportBatch::sole();
}

it('uploads a CSV into a pending batch and opens its preview', function () {
    $this->actingAs($this->admin);

    livewire(ImportWizard::class)
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('example.csv', file_get_contents(base_path('content/schema/example.csv')))])
        ->call('upload')
        ->assertHasNoFormErrors()
        ->assertRedirect(ImportBatchResource::getUrl('view', ['record' => 1]));

    $batch = ImportBatch::sole();
    expect($batch->format)->toBe(ImportFormat::Csv)
        ->and($batch->original_filename)->toBe('example.csv')
        ->and($batch->user_id)->toBe($this->admin->id)
        ->and($batch->total_rows)->toBe(3)
        ->and(Question::count())->toBe(0);
});

it('shows a file-level error on the upload form', function () {
    $this->actingAs($this->admin);

    livewire(ImportWizard::class)
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('broken.json', '{"schema_version": 3, "items": []}')])
        ->call('upload')
        ->assertHasFormErrors(['file']);

    expect(ImportBatch::count())->toBe(0);
});

it('flags media in an uploaded JSON as attach-in-editor', function () {
    $this->actingAs($this->admin);
    $json = json_encode(['schema_version' => 1, 'items' => [[
        'subtest' => 'numerical', 'topic' => 'word_problems', 'stem' => 'Read the chart.', 'choices' => ['1', '2'],
        'answer' => 'A', 'explanation' => 'x', 'media' => [['path' => 'media/chart.png', 'alt' => 'Chart']],
    ]]]);

    livewire(ImportWizard::class)
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('media.json', $json)])
        ->call('upload')
        ->assertHasNoFormErrors();

    expect(array_column(ImportBatch::sole()->rows[0]->flags, 'code'))->toBe(['media_missing']);
});

it('previews rows with errors and flags, and error rows cannot be selected (AC-21)', function () {
    $batch = uploadedBatch($this);
    [$ok, $broken, $flagged] = $batch->rows;

    livewire(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->assertCanSeeTableRecords($batch->rows)
        ->assertSee(__('An item needs 2–5 choices; this row has :count.', ['count' => 1]))
        ->assertSee(__('No answer key'))
        ->assertTableBulkActionExists('approve')
        ->tap(function ($component) use ($ok, $broken) {
            $table = $component->instance()->getTable();
            expect($table->isRecordSelectable($ok))->toBeTrue()
                ->and($table->isRecordSelectable($broken))->toBeFalse();
        });
});

it('approves selected rows as draft or needs_review under a private source and updates counts', function () {
    $batch = uploadedBatch($this);
    [$ok, $broken, $flagged] = $batch->rows;

    livewire(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->selectTableRecords([$ok->id, $flagged->id])
        ->callAction(TestAction::make('approve')->table()->bulk());

    $batch->refresh();
    expect($batch->approved_rows)->toBe(2)
        ->and($ok->refresh()->question->status)->toBe(QuestionStatus::Draft)
        ->and($flagged->refresh()->question->status)->toBe(QuestionStatus::NeedsReview)
        ->and($ok->question->source->visibility)->toBe(SourceVisibility::Private)
        ->and($broken->refresh()->decision)->toBe(ImportRowDecision::Pending);
});

it('approves all valid rows from the batch page', function () {
    $batch = uploadedBatch($this);

    livewire(ViewImportBatch::class, ['record' => $batch->getRouteKey()])
        ->callAction('approveValid');

    expect($batch->refresh()->approved_rows)->toBe(2)
        ->and(Question::count())->toBe(2);
});

it('rejects selected rows and updates counts', function () {
    $batch = uploadedBatch($this);

    livewire(RowsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewImportBatch::class])
        ->selectTableRecords([$batch->rows[2]->id])
        ->callAction(TestAction::make('reject')->table()->bulk());

    expect($batch->refresh()->rejected_rows)->toBe(1)
        ->and($batch->rows[2]->refresh()->decision)->toBe(ImportRowDecision::Rejected)
        ->and(Question::count())->toBe(0);
});

it('rejects all rows with errors from the batch page', function () {
    $batch = uploadedBatch($this);

    livewire(ViewImportBatch::class, ['record' => $batch->getRouteKey()])
        ->callAction('rejectErrors');

    expect($batch->rows[1]->refresh()->decision)->toBe(ImportRowDecision::Rejected)
        ->and($batch->refresh()->rejected_rows)->toBe(1);
});

it('lists batches with status and counts', function () {
    $batch = uploadedBatch($this);

    livewire(ListImportBatches::class)
        ->assertCanSeeTableRecords([$batch])
        ->assertTableColumnStateSet('total_rows', 3, $batch)
        ->assertTableColumnStateSet('valid_rows', 2, $batch);
});

it('shows the file’s warnings on the batch page', function () {
    $this->actingAs($this->admin);
    $json = json_encode(['schema_version' => 1, 'batch' => ['default_source' => ['name' => 'Reviewer X', 'visibility' => 'public']],
        'items' => [['subtest' => 'numerical', 'topic' => 'word_problems', 'stem' => 'x?', 'choices' => ['1', '2'], 'answer' => 'A']]]);

    livewire(ImportWizard::class)
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('pub.json', $json)])
        ->call('upload');

    livewire(ViewImportBatch::class, ['record' => ImportBatch::sole()->getRouteKey()])
        ->assertSee(__('The file asked for a public source; “:name” will be created as private.', ['name' => 'Reviewer X']), escape: false);
});

it('gives users a 403', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ImportBatchResource::getUrl('index'))->assertForbidden();
    $this->get(ImportBatchResource::getUrl('upload'))->assertForbidden();
});

it('serves the pages to admins', function () {
    $batch = uploadedBatch($this);

    $this->get(ImportBatchResource::getUrl('index'))->assertOk();
    $this->get(ImportBatchResource::getUrl('upload'))->assertOk();
    $this->get(ImportBatchResource::getUrl('view', ['record' => $batch]))->assertOk();
});
