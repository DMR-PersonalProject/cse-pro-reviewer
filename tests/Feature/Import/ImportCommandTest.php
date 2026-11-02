<?php

use App\Enums\ImportBatchStatus;
use App\Enums\QuestionStatus;
use App\Models\ImportBatch;
use App\Models\Media;
use App\Models\Question;
use App\Models\Source;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    Storage::fake('public');
    $this->staged = sys_get_temp_dir().'/cse-cli-'.uniqid();
    mkdir($this->staged.'/media', recursive: true);
    config(['cse.staged_path' => $this->staged]);
});

afterEach(function () {
    foreach ([...glob($this->staged.'/media/*'), ...glob($this->staged.'/*.*')] as $file) {
        unlink($file);
    }

    rmdir($this->staged.'/media');
    rmdir($this->staged);
});

function stageFile(string $root, string $name, array $items): string
{
    $path = $root.'/'.$name;
    file_put_contents($path, json_encode(['schema_version' => 1, 'items' => $items]));

    return $path;
}

function cliItem(array $overrides = []): array
{
    return [
        'subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => fake()->unique()->sentence(12),
        'choices' => ['1', '2'], 'answer' => 'A', 'explanation' => 'Because.', ...$overrides,
    ];
}

it('writes nothing on a dry run and prints a summary', function () {
    $this->artisan('cse:import', ['path' => 'content/schema/example.json', '--dry-run' => true])
        ->expectsOutputToContain('missing_answer_key')
        ->expectsOutputToContain(__('Dry run: nothing was saved.'))
        ->assertSuccessful();

    expect(ImportBatch::count())->toBe(0)
        ->and(Question::count())->toBe(0)
        ->and(Source::count())->toBe(0);
});

it('creates a pending batch from a path relative to the project', function () {
    expect(Artisan::call('cse:import', ['path' => 'content/schema/example.json']))->toBe(0);

    $batch = ImportBatch::sole();
    // Use the real id: MySQL doesn't roll back auto-increment between tests.
    expect(Artisan::output())->toContain(__('Batch #:id created with :total rows (:valid valid). Review it in /admin.', ['id' => $batch->id, 'total' => 4, 'valid' => 4]));
    expect($batch->status)->toBe(ImportBatchStatus::Pending)
        ->and($batch->user_id)->toBeNull()
        ->and($batch->rows()->count())->toBe(4)
        ->and(Question::count())->toBe(0);
});

it('approves valid rows as draft or needs_review only, leaving error rows pending', function () {
    $path = stageFile($this->staged, 'batch.json', [cliItem(), cliItem(['answer' => null]), cliItem(['choices' => ['1']])]);

    $this->artisan('cse:import', ['path' => $path, '--approve-valid' => true])
        ->expectsOutputToContain(__('Approved :count rows.', ['count' => 2]))
        ->assertSuccessful();

    expect(Question::pluck('status')->map->value->sort()->values()->all())->toBe(['draft', 'needs_review'])
        ->and(Question::where('status', QuestionStatus::Published)->count())->toBe(0)
        ->and(ImportBatch::sole()->rows()->where('decision', 'pending')->count())->toBe(1);
});

it('copies and validates staged media', function () {
    imagepng(imagecreatetruecolor(40, 30), $this->staged.'/media/ok.png');
    file_put_contents($this->staged.'/media/bad.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $path = stageFile($this->staged, 'media.json', [
        cliItem(['media' => [['path' => 'media/ok.png', 'alt' => 'A chart']]]),
        cliItem(['media' => [['path' => 'media/bad.png', 'alt' => 'A chart']]]),
    ]);

    $this->artisan('cse:import', ['path' => $path, '--approve-valid' => true])
        ->expectsOutputToContain(__('Unsupported image type. Use PNG, JPG or WebP.'))
        ->assertSuccessful();

    $media = Media::sole();
    expect(Storage::disk('public')->exists($media->path))->toBeTrue()
        ->and(Question::count())->toBe(1);
});

it('fails with a non-zero exit code on a file-level error', function (string $contents) {
    file_put_contents($this->staged.'/broken.json', $contents);

    $this->artisan('cse:import', ['path' => $this->staged.'/broken.json'])->assertFailed();

    expect(ImportBatch::count())->toBe(0);
})->with(['malformed' => '{"schema_version": 1, "items": [', 'wrong version' => '{"schema_version": 9, "items": []}']);

it('fails when the file does not exist', function () {
    $this->artisan('cse:import', ['path' => 'content/staged/nope.json'])->assertFailed();
});
