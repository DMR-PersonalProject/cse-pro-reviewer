<?php

use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\ImportParser;
use App\Enums\ImportFormat;

function parser(): ImportParser
{
    return app(ImportParser::class);
}

it('parses the example JSON file', function () {
    $parsed = parser()->parseFile(base_path('content/schema/example.json'));

    expect($parsed->format)->toBe(ImportFormat::Json)
        ->and($parsed->name)->toBe('example-mixed-4')
        ->and($parsed->defaultSource)->toBe(['name' => 'Original — import example', 'type' => 'original', 'visibility' => 'private'])
        ->and($parsed->passages)->toHaveCount(1)
        ->and($parsed->passages[0]['ref'])->toBe('p1')
        ->and($parsed->rows)->toHaveCount(4)
        ->and(array_column($parsed->rows, 'row_number'))->toBe([1, 2, 3, 4]);

    $first = $parsed->rows[0];
    expect($first['subtest'])->toBe('numerical')
        ->and($first['choices'])->toBe(['28', '30', '32', '36'])
        ->and($first['answer'])->toBe('B')
        ->and($first['tags'])->toBe(['differences'])
        ->and($first['provenance'])->toBe(['file' => 'example.json', 'page' => '1', 'notes' => null])
        ->and($first['extraction'])->toBe(['confidence' => 1.0, 'needs_review' => false, 'notes' => null]);

    expect($parsed->rows[1]['passage_ref'])->toBe('p1')
        ->and($parsed->rows[3]['answer'])->toBeNull()
        ->and($parsed->rows[3]['explanation'])->toBeNull()
        ->and($parsed->rows[3]['lock_choice_order'])->toBeTrue();
});

it('parses the example CSV file', function () {
    $parsed = parser()->parseFile(base_path('content/schema/example.csv'));

    expect($parsed->format)->toBe(ImportFormat::Csv)
        ->and($parsed->name)->toBe('example')
        ->and($parsed->defaultSource)->toBeNull()
        ->and($parsed->passages)->toBe([])
        ->and($parsed->rows)->toHaveCount(3);

    $second = $parsed->rows[1];
    expect($second['choices'])->toBe(['Careful', 'Careless', 'Quick', 'Generous'])
        ->and($second['tags'])->toBe(['synonyms', 'adjectives'])
        ->and($second['passage_ref'])->toBeNull()
        ->and($second['media'])->toBe([])
        ->and($second['extraction']['confidence'])->toBe(1.0);
});

it('keeps CSV line numbers as row numbers, skipping blank lines', function () {
    $csv = implode("\n", [
        ImportParser::CSV_HEADER_LINE,
        'numerical,basic_operations,en,1 + 1?,1,2,,,,B,,,,,,,',
        '',
        'numerical,basic_operations,en,2 + 2?,3,4,,,,,,,,,,,',
    ]);

    $rows = parser()->parse($csv, ImportFormat::Csv, 'x.csv')->rows;

    expect(array_column($rows, 'row_number'))->toBe([2, 4])
        ->and($rows[1]['answer'])->toBeNull()
        ->and($rows[1]['explanation'])->toBeNull()
        ->and($rows[1]['extraction']['confidence'])->toBeNull();
});

it('accepts a UTF-8 BOM and header case or spacing', function () {
    $csv = "\u{FEFF}".strtoupper(str_replace(',', ', ', ImportParser::CSV_HEADER_LINE))."\n"
        .'verbal,word_meaning,fil,Ano?,A,B,,,,a,,,,,,,';

    $row = parser()->parse($csv, ImportFormat::Csv, 'x.csv')->rows[0];

    expect($row['language'])->toBe('fil')
        ->and($row['answer'])->toBe('A');
});

it('refuses a file-level problem', function (string $contents, ImportFormat $format, string $message) {
    expect(fn () => parser()->parse($contents, $format, 'x'))
        ->toThrow(ImportFileException::class, $message);
})->with([
    'malformed JSON' => ['{"schema_version": 1, "items": [', ImportFormat::Json, 'not valid JSON'],
    'JSON not an object' => ['[1, 2]', ImportFormat::Json, 'must be an object'],
    'wrong schema_version' => ['{"schema_version": 2, "items": []}', ImportFormat::Json, 'schema_version'],
    'missing schema_version' => ['{"items": []}', ImportFormat::Json, 'schema_version'],
    'no items' => ['{"schema_version": 1, "items": []}', ImportFormat::Json, 'no items'],
    'missing CSV header' => ["numerical,basic_operations,en,1 + 1?,1,2,,,,B,,,,,,,\n", ImportFormat::Csv, 'header'],
    'missing CSV column' => ["subtest,topic,language,stem\nverbal,word_meaning,en,x\n", ImportFormat::Csv, 'header'],
    'empty CSV' => ['', ImportFormat::Csv, 'header'],
    'CSV header only' => [ImportParser::CSV_HEADER_LINE."\n", ImportFormat::Csv, 'no items'],
]);

it('refuses an unknown file type', function () {
    expect(fn () => parser()->parseFile(base_path('content/README.md')))
        ->toThrow(ImportFileException::class, 'JSON or CSV');
});

it('normalizes loose JSON values without guessing', function () {
    $json = json_encode([
        'schema_version' => 1,
        'items' => [[
            'subtest' => ' Verbal ',
            'topic' => 'word_meaning',
            'stem' => '  Pick one.  ',
            'choices' => [' a ', '', 'b'],
            'answer' => ' ',
            'explanation' => '',
            'tags' => ['x', ' ', 'x'],
        ], [
            'subtest' => 'verbal',
            'choices' => 'not a list',
        ]],
    ]);

    [$first, $second] = parser()->parse($json, ImportFormat::Json, 'x.json')->rows;

    expect($first['subtest'])->toBe('verbal')
        ->and($first['stem'])->toBe('Pick one.')
        ->and($first['choices'])->toBe(['a', '', 'b'])
        ->and($first['answer'])->toBeNull()
        ->and($first['explanation'])->toBeNull()
        ->and($first['tags'])->toBe(['x'])
        ->and($first['language'])->toBe('en')
        ->and($second['choices'])->toBe([])
        ->and($second['stem'])->toBe('')
        ->and($second['topic'])->toBe('');
});
