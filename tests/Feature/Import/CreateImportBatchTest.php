<?php

use App\Domain\Import\CreateImportBatch;
use App\Domain\Import\ImportParser;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportFormat;
use App\Enums\ImportRowDecision;
use App\Enums\SourceVisibility;
use App\Models\AuditLog;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;

beforeEach(fn () => $this->seed(TaxonomySeeder::class));

function batchFromJson(array $data, ?User $user = null)
{
    $import = app(ImportParser::class)->parse(json_encode(['schema_version' => 1, ...$data]), ImportFormat::Json, 'staged.json');

    return app(CreateImportBatch::class)->handle($import, $user, null);
}

it('stores the example file as a pending batch with counts and nothing in the bank', function () {
    $admin = User::factory()->admin()->create();
    $import = app(ImportParser::class)->parseFile(base_path('content/schema/example.json'));

    $batch = app(CreateImportBatch::class)->handle($import, $admin, base_path('content/staged'));

    expect($batch->status)->toBe(ImportBatchStatus::Pending)
        ->and($batch->user->is($admin))->toBeTrue()
        ->and($batch->name)->toBe('example-mixed-4')
        ->and($batch->original_filename)->toBe('example.json')
        ->and($batch->format)->toBe(ImportFormat::Json)
        ->and($batch->total_rows)->toBe(4)
        ->and($batch->valid_rows)->toBe(4)
        ->and($batch->approved_rows)->toBe(0)
        ->and($batch->rejected_rows)->toBe(0)
        ->and($batch->meta['default_source']['name'])->toBe('Original — import example')
        ->and($batch->meta['passages']['p1']['body'])->toStartWith('Every Saturday')
        ->and($batch->rows)->toHaveCount(4)
        ->and($batch->rows->every(fn ($row) => $row->decision === ImportRowDecision::Pending))->toBeTrue()
        ->and($batch->rows[3]->row_number)->toBe(4)
        ->and(array_column($batch->rows[3]->flags, 'code'))->toBe(['missing_answer_key', 'missing_explanation', 'low_confidence', 'extractor_flagged'])
        ->and($batch->rows[0]->payload['stem'])->toBe('What comes next: 2, 6, 12, 20, __?');

    expect(Question::count())->toBe(0)
        ->and(Passage::count())->toBe(0)
        ->and(Source::count())->toBe(0);
});

it('counts rows with errors as not valid and keeps their errors', function () {
    $batch = batchFromJson(['items' => [
        ['subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => '1 + 1?', 'choices' => ['1', '2'], 'answer' => 'B'],
        ['subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => '2 + 2?', 'choices' => ['4'], 'answer' => 'A'],
    ]]);

    expect($batch->total_rows)->toBe(2)
        ->and($batch->valid_rows)->toBe(1)
        ->and($batch->rows[0]->hasErrors())->toBeFalse()
        ->and($batch->rows[1]->errors)->toContain(__('An item needs 2–5 choices; this row has :count.', ['count' => 1]));
});

it('keeps the bank duplicate on the row', function () {
    $existing = Question::factory()->create([
        'subtest_id' => Subtest::where('code', 'numerical')->value('id'),
        'topic_id' => Topic::where('code', 'basic_operations')->value('id'),
        'stem' => 'What is the sum of twelve and thirty in this problem?',
    ]);

    $batch = batchFromJson(['items' => [
        ['subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => 'What is the sum of twelve and thirty in this problem', 'choices' => ['42', '43'], 'answer' => 'A', 'explanation' => 'Add.'],
    ]]);

    expect($batch->rows[0]->duplicate_of_question_id)->toBe($existing->id);
});

it('warns and audits when the file asks for a public source, without failing', function () {
    $batch = batchFromJson([
        'batch' => ['default_source' => ['name' => 'Reviewer X', 'type' => 'reviewer', 'visibility' => 'public']],
        'items' => [['subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => '1 + 1?', 'choices' => ['1', '2'], 'answer' => 'B']],
    ], User::factory()->admin()->create());

    expect($batch->meta['warnings'])->toBe([__('The file asked for a public source; “:name” will be created as private.', ['name' => 'Reviewer X'])])
        ->and(Source::count())->toBe(0);

    $log = AuditLog::where('action', 'import.public_source_requested')->sole();
    expect($log->subject_id)->toBe($batch->id)
        ->and($log->meta)->toBe(['source' => 'Reviewer X']);
});

it('warns that an existing source is reused unchanged', function () {
    Source::factory()->create(['name' => 'Reviewer X', 'visibility' => SourceVisibility::Public]);

    $batch = batchFromJson([
        'batch' => ['default_source' => ['name' => 'Reviewer X', 'visibility' => 'private']],
        'items' => [['subtest' => 'numerical', 'topic' => 'basic_operations', 'stem' => '1 + 1?', 'choices' => ['1', '2'], 'answer' => 'B']],
    ]);

    expect($batch->meta['warnings'])->toBe([__('The existing source “:name” (:visibility) will be used as it is.', [
        'name' => 'Reviewer X', 'visibility' => SourceVisibility::Public->label(),
    ])]);
});
