<?php

use App\Domain\Import\ImportParser;
use App\Domain\Import\RowValidator;
use App\Enums\ImportFormat;
use Database\Seeders\TaxonomySeeder;

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->staged = sys_get_temp_dir().'/cse-staged-'.uniqid();
    mkdir($this->staged.'/media', recursive: true);
});

afterEach(function () {
    foreach (glob($this->staged.'/media/*') as $file) {
        unlink($file);
    }

    rmdir($this->staged.'/media');
    rmdir($this->staged);
});

/**
 * Errors for each row of a JSON import built from the given items and passages.
 *
 * @return list<list<string>>
 */
function jsonRowErrors(array $items, array $passages = [], ?string $mediaRoot = null): array
{
    $json = json_encode(['schema_version' => 1, 'passages' => $passages, 'items' => $items]);
    $import = app(ImportParser::class)->parse($json, ImportFormat::Json, 'x.json');

    return app(RowValidator::class)->validate($import, $mediaRoot);
}

function validItem(array $overrides = []): array
{
    return [
        'subtest' => 'numerical', 'topic' => 'number_sequences', 'stem' => 'What comes next: 1, 2, 3, __?',
        'choices' => ['4', '5', '6'], 'answer' => 'A', ...$overrides,
    ];
}

function stagedImage(string $root, string $name, string $kind, int $padToBytes = 0): void
{
    $path = $root.'/media/'.$name;
    $image = imagecreatetruecolor(40, 30);

    match ($kind) {
        'png' => imagepng($image, $path),
        'svg' => file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>'),
    };

    if ($padToBytes > 0) {
        file_put_contents($path, str_repeat("\0", $padToBytes - filesize($path)), FILE_APPEND);
    }
}

it('finds no errors in the example files', function (string $file) {
    $import = app(ImportParser::class)->parseFile(base_path('content/schema/'.$file));

    expect(app(RowValidator::class)->validate($import, base_path('content/staged')))
        ->each->toBe([]);
})->with(['example.json', 'example.csv']);

it('refuses a CSV row with 1 choice (AC-21)', function () {
    $csv = ImportParser::CSV_HEADER_LINE."\n".'numerical,basic_operations,en,1 + 1?,2,,,,,A,,,,,,,';
    $import = app(ImportParser::class)->parse($csv, ImportFormat::Csv, 'x.csv');

    expect(app(RowValidator::class)->validate($import, null)[0])
        ->toContain(__('An item needs 2–5 choices; this row has :count.', ['count' => 1]));
});

it('refuses more than 5 choices', function () {
    expect(jsonRowErrors([validItem(['choices' => ['1', '2', '3', '4', '5', '6']])])[0])
        ->toContain(__('An item needs 2–5 choices; this row has :count.', ['count' => 6]));
});

it('refuses an SVG or an image over 2 MB (AC-21a)', function (string $kind, int $pad, string $message) {
    stagedImage($this->staged, 'figure.png', $kind, $pad);

    $errors = jsonRowErrors([validItem(['media' => [['path' => 'media/figure.png', 'alt' => 'A figure']]])], mediaRoot: $this->staged);

    expect($errors[0])->toContain(__('Image :path: :error', ['path' => 'media/figure.png', 'error' => $message]));
})->with([
    'svg named .png' => ['svg', 0, 'Unsupported image type. Use PNG, JPG or WebP.'],
    'png over 2 MB' => ['png', 2 * 1024 * 1024 + 1, 'File too large. The limit is 2 MB.'],
]);

it('accepts a valid staged image, and leaves missing files and alt text to the flagger', function () {
    stagedImage($this->staged, 'ok.png', 'png');

    $errors = jsonRowErrors([
        validItem(['media' => [['path' => 'media/ok.png', 'alt' => 'A chart']]]),
        validItem(['media' => [['path' => 'media/ok.png', 'alt' => null]]]),
        validItem(['media' => [['path' => 'media/missing.png', 'alt' => 'A chart']]]),
    ], mediaRoot: $this->staged);

    expect($errors)->toBe([[], [], []]);
});

it('refuses a media path outside the staged folder', function (string $path) {
    expect(jsonRowErrors([validItem(['media' => [['path' => $path, 'alt' => 'x']]])], mediaRoot: $this->staged)[0])
        ->toContain(__('Image :path must be a relative path inside content/staged/.', ['path' => $path]));
})->with(['../secret.png', 'media/../../x.png', '/etc/passwd', 'C:\\x.png']);

it('refuses an unknown subtest or topic', function () {
    $errors = jsonRowErrors([
        validItem(['subtest' => 'logic']),
        validItem(['topic' => 'word_meaning']),
        validItem(['topic' => '']),
    ]);

    expect($errors[0])->toContain(__('Unknown subtest “:code”.', ['code' => 'logic']))
        ->and($errors[1])->toContain(__('Unknown topic “:code” for subtest :subtest.', ['code' => 'word_meaning', 'subtest' => 'numerical']))
        ->and($errors[2])->toContain(__('Unknown topic “:code” for subtest :subtest.', ['code' => '', 'subtest' => 'numerical']));
});

it('refuses an empty stem or an empty choice', function () {
    $errors = jsonRowErrors([validItem(['stem' => '  ']), validItem(['choices' => ['4', ' ', '6']])]);

    expect($errors[0])->toContain(__('The stem is empty.'))
        ->and($errors[1])->toContain(__('Choice :letter is empty.', ['letter' => 'B']));
});

it('refuses an answer that does not match a choice', function (string $answer) {
    expect(jsonRowErrors([validItem(['answer' => $answer])])[0])
        ->toContain(__('The answer “:answer” doesn’t match a choice.', ['answer' => mb_strtoupper($answer)]));
})->with(['D', 'E', 'x', '1', 'AB']);

it('accepts a missing answer as a flag, not an error', function () {
    expect(jsonRowErrors([validItem(['answer' => null])])[0])->toBe([]);
});

it('refuses an unknown language, difficulty or confidence', function () {
    $errors = jsonRowErrors([
        validItem(['language' => 'es']),
        validItem(['difficulty' => 'extreme']),
        validItem(['extraction' => ['confidence' => 1.5]]),
    ]);

    expect($errors[0])->toContain(__('Unknown language “:code”.', ['code' => 'es']))
        ->and($errors[1])->toContain(__('Unknown difficulty “:code”.', ['code' => 'extreme']))
        ->and($errors[2])->toContain(__('Confidence must be between 0 and 1.'));
});

it('refuses an unknown passage_ref', function () {
    expect(jsonRowErrors([validItem(['passage_ref' => 'p9'])])[0])
        ->toContain(__('Unknown passage_ref “:ref”.', ['ref' => 'p9']));
});

it('refuses a passage ref used by more than one passage', function () {
    $passage = ['ref' => 'p1', 'subtest' => 'verbal', 'topic' => 'reading_comprehension', 'body' => 'Text'];
    $item = validItem(['subtest' => 'verbal', 'topic' => 'reading_comprehension', 'passage_ref' => 'p1']);

    $errors = jsonRowErrors([$item, validItem()], [$passage, $passage]);

    expect($errors[0])->toContain(__('Passage ref “:ref” is used by more than one passage.', ['ref' => 'p1']))
        ->and($errors[1])->toBe([]);
});

it('passes a passage’s own errors to its items', function () {
    $passage = ['ref' => 'p1', 'subtest' => 'verbal', 'topic' => 'nope', 'body' => ''];
    $item = validItem(['subtest' => 'verbal', 'topic' => 'nope', 'passage_ref' => 'p1']);

    $errors = jsonRowErrors([$item], [$passage]);

    expect($errors[0])->toContain(__('Passage :ref: :error', [
        'ref' => 'p1',
        'error' => __('Unknown topic “:code” for subtest :subtest.', ['code' => 'nope', 'subtest' => 'verbal']),
    ]))->toContain(__('Passage :ref: :error', ['ref' => 'p1', 'error' => __('The passage has no text and no image.')]));
});

it('refuses an item whose subtest or topic differs from its passage', function () {
    $passage = ['ref' => 'p1', 'subtest' => 'verbal', 'topic' => 'reading_comprehension', 'body' => 'Text'];

    $errors = jsonRowErrors([validItem(['subtest' => 'verbal', 'topic' => 'word_meaning', 'passage_ref' => 'p1'])], [$passage]);

    expect($errors[0])->toContain(__('The item’s subtest and topic must match passage :ref.', ['ref' => 'p1']));
});
