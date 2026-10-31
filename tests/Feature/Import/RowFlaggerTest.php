<?php

use App\Domain\Content\StemNormalizer;
use App\Domain\Import\ImportParser;
use App\Domain\Import\RowFlagger;
use App\Enums\ImportFlag;
use App\Enums\ImportFormat;
use App\Enums\NeedsReviewReason;
use App\Models\Question;
use App\Models\Subtest;
use Database\Seeders\TaxonomySeeder;

const FLAG_BASE_STEM = 'A farmer sells mangoes at the public market every morning and earns enough to send his children to school';

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->staged = sys_get_temp_dir().'/cse-flag-'.uniqid();
    mkdir($this->staged.'/media', recursive: true);
    imagepng(imagecreatetruecolor(40, 30), $this->staged.'/media/chart.png');
});

afterEach(function () {
    unlink($this->staged.'/media/chart.png');
    rmdir($this->staged.'/media');
    rmdir($this->staged);
});

/**
 * FLAG_BASE_STEM (normalized) with the last share of characters replaced, so similarity ≈ 1 - $share.
 */
function flagStemVariant(float $share): string
{
    $base = app(StemNormalizer::class)->normalize(FLAG_BASE_STEM);
    $keep = (int) round(mb_strlen($base) * (1 - $share));

    return mb_substr($base, 0, $keep).str_repeat('z', mb_strlen($base) - $keep);
}

function flagItem(array $overrides = []): array
{
    return [
        'subtest' => 'numerical', 'topic' => 'word_problems', 'stem' => 'How many mangoes are left?',
        'choices' => ['1', '2'], 'answer' => 'A', 'explanation' => 'Because.',
        'provenance' => ['file' => 'reviewer-x.pdf', 'page' => 12],
        'extraction' => ['confidence' => 0.95, 'needs_review' => false], ...$overrides,
    ];
}

/**
 * @return list<array{flags: list<array{code: string, detail: ?string}>, duplicate_of_question_id: ?int}>
 */
function flagRows(array $items, array $passages = [], ?string $mediaRoot = null): array
{
    $json = json_encode(['schema_version' => 1, 'passages' => $passages, 'items' => $items]);

    return app(RowFlagger::class)->flag(app(ImportParser::class)->parse($json, ImportFormat::Json, 'x.json'), $mediaRoot);
}

function flagCodes(array $result): array
{
    return array_column($result['flags'], 'code');
}

it('flags nothing on a complete row', function () {
    expect(flagRows([flagItem()])[0])->toBe(['flags' => [], 'duplicate_of_question_id' => null]);
});

it('flags a missing answer key without filling one in (AC-20)', function () {
    expect(flagCodes(flagRows([flagItem(['answer' => null])])[0]))->toBe(['missing_answer_key']);
});

it('flags a missing explanation', function () {
    expect(flagCodes(flagRows([flagItem(['explanation' => null])])[0]))->toBe(['missing_explanation']);
});

it('flags a confidence below 0.8 only', function () {
    $rows = flagRows([
        flagItem(['extraction' => ['confidence' => 0.79]]),
        flagItem(['extraction' => ['confidence' => 0.8], 'stem' => 'What is 7 times 8?']),
        flagItem(['extraction' => ['confidence' => null], 'stem' => 'Which number is prime: 9, 11 or 15?']),
    ]);

    expect(flagCodes($rows[0]))->toBe(['low_confidence'])
        ->and(flagCodes($rows[1]))->toBe([])
        ->and(flagCodes($rows[2]))->toBe([]);
});

it('flags a row the extractor marked for review, keeping its notes', function () {
    $row = flagRows([flagItem(['extraction' => ['confidence' => 0.9, 'needs_review' => true, 'notes' => 'Smudged choice C.']])])[0];

    expect($row['flags'])->toBe([['code' => 'extractor_flagged', 'detail' => 'Smudged choice C.']]);
});

it('flags media that is not in the staged folder', function () {
    $row = flagRows([flagItem(['media' => [['path' => 'media/gone.png', 'alt' => 'A chart']]])], mediaRoot: $this->staged)[0];

    expect(flagCodes($row))->toBe(['media_missing'])
        ->and($row['flags'][0]['detail'])->toContain('media/gone.png')->toContain('reviewer-x.pdf');
});

it('flags all media as attach-in-editor when no files come along (admin upload)', function () {
    $row = flagRows([flagItem(['media' => [['path' => 'media/chart.png', 'alt' => 'A chart']]])])[0];

    expect(flagCodes($row))->toBe(['media_missing'])
        ->and($row['flags'][0]['detail'])->toContain(__('attach it in the editor'));
});

it('flags an image without alt text, naming the file and where it came from', function () {
    $row = flagRows([flagItem(['media' => [['path' => 'media/chart.png', 'alt' => null]]])], mediaRoot: $this->staged)[0];

    expect(flagCodes($row))->toBe(['missing_alt_text'])
        ->and($row['flags'][0]['detail'])
        ->toContain('media/chart.png')
        ->toContain('reviewer-x.pdf')
        ->toContain('12');
});

it('flags passage media on every item of the passage', function () {
    $passage = ['ref' => 'p1', 'subtest' => 'numerical', 'topic' => 'word_problems', 'body' => 'Table',
        'media' => [['path' => 'media/chart.png', 'alt' => '']]];

    $rows = flagRows([flagItem(['passage_ref' => 'p1']), flagItem(['passage_ref' => 'p1', 'stem' => 'Other?'])], [$passage], $this->staged);

    expect(flagCodes($rows[0]))->toBe(['missing_alt_text'])
        ->and(flagCodes($rows[1]))->toBe(['missing_alt_text']);
});

it('flags a 95% match in the bank and links to it (AC-22)', function () {
    $subtest = Subtest::where('code', 'numerical')->first();
    $original = Question::factory()->create(['subtest_id' => $subtest->id, 'topic_id' => $subtest->topics()->first()->id, 'stem' => FLAG_BASE_STEM]);

    $rows = flagRows([flagItem(['stem' => flagStemVariant(0.05)]), flagItem(['stem' => flagStemVariant(0.30)])]);

    expect(flagCodes($rows[0]))->toBe(['possible_duplicate'])
        ->and($rows[0]['duplicate_of_question_id'])->toBe($original->id)
        ->and($rows[0]['flags'][0]['detail'])->toContain('#'.$original->id)
        ->and(flagCodes($rows[1]))->toBe([]);
});

it('flags a duplicate within the batch on the later row', function () {
    $rows = flagRows([
        flagItem(['stem' => FLAG_BASE_STEM]),
        flagItem(['stem' => flagStemVariant(0.05)]),
        flagItem(['stem' => flagStemVariant(0.05), 'subtest' => 'verbal', 'topic' => 'word_meaning']),
    ]);

    expect(flagCodes($rows[0]))->toBe([])
        ->and(flagCodes($rows[1]))->toBe(['possible_duplicate'])
        ->and($rows[1]['duplicate_of_question_id'])->toBeNull()
        ->and($rows[1]['flags'][0]['detail'])->toContain(__('row :row in this file', ['row' => 1]))
        ->and(flagCodes($rows[2]))->toBe([]);
});

it('maps each flag to a needs-review reason', function () {
    expect(collect(ImportFlag::cases())->mapWithKeys(fn (ImportFlag $flag) => [$flag->value => $flag->reason()])->all())
        ->toBe([
            'missing_answer_key' => NeedsReviewReason::MissingAnswerKey,
            'missing_explanation' => NeedsReviewReason::MissingExplanation,
            'low_confidence' => NeedsReviewReason::LowConfidence,
            'media_missing' => NeedsReviewReason::MissingMedia,
            'missing_alt_text' => NeedsReviewReason::MissingMedia,
            'possible_duplicate' => NeedsReviewReason::PossibleDuplicate,
            'extractor_flagged' => NeedsReviewReason::FlaggedInSource,
        ]);
});
