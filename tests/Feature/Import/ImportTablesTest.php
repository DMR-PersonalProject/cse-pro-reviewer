<?php

use App\Enums\ImportBatchStatus;
use App\Enums\ImportFormat;
use App\Enums\ImportRowDecision;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Question;
use App\Models\User;

function makeBatch(array $attributes = []): ImportBatch
{
    return ImportBatch::create([
        'user_id' => User::factory()->admin()->create()->id,
        'name' => 'example',
        'original_filename' => 'example.json',
        'format' => ImportFormat::Json,
        ...$attributes,
    ]);
}

it('creates a pending batch with zero counts and meta', function () {
    $batch = makeBatch(['meta' => ['passages' => ['p1' => ['body' => 'Text']]]]);

    $batch->refresh();
    expect($batch->status)->toBe(ImportBatchStatus::Pending)
        ->and($batch->format)->toBe(ImportFormat::Json)
        ->and($batch->total_rows)->toBe(0)
        ->and($batch->approved_rows)->toBe(0)
        ->and($batch->meta['passages']['p1']['body'])->toBe('Text')
        ->and($batch->user)->toBeInstanceOf(User::class);
});

it('defaults a row decision to pending and casts its json', function () {
    $row = makeBatch()->rows()->create([
        'row_number' => 3,
        'payload' => ['stem' => 'What?'],
        'errors' => [],
        'flags' => [['code' => 'missing_answer_key']],
    ]);

    $row->refresh();
    expect($row->decision)->toBe(ImportRowDecision::Pending)
        ->and($row->payload['stem'])->toBe('What?')
        ->and($row->flags[0]['code'])->toBe('missing_answer_key')
        ->and($row->batch)->toBeInstanceOf(ImportBatch::class);
});

it('deletes rows with their batch', function () {
    $batch = makeBatch();
    $batch->rows()->create(['row_number' => 1, 'payload' => []]);

    $batch->delete();

    expect(ImportRow::count())->toBe(0);
});

it('links a question to the batch it came from', function () {
    $batch = makeBatch();
    $question = Question::factory()->create(['import_batch_id' => $batch->id]);

    expect($question->importBatch->is($batch))->toBeTrue()
        ->and($batch->questions()->count())->toBe(1);
});
