<?php

use App\Domain\Scoring\Formulas\GeneralRatingV1;

it('AC-13: rates 136/170 as 80.00 and 135/170 as 79.41', function () {
    $formula = new GeneralRatingV1;

    expect($formula->hundredths(136, 170))->toBe(8000)
        ->and($formula->hundredths(135, 170))->toBe(7941)
        ->and($formula->percent(136, 170))->toBe('80.00')
        ->and($formula->percent(135, 170))->toBe('79.41');
});

it('rounds half up to 2 decimals', function (int $correct, int $total, string $expected) {
    expect((new GeneralRatingV1)->percent($correct, $total))->toBe($expected);
})->with([
    '1/8 = 12.5 exactly' => [1, 8, '12.50'],
    '1/3 = 33.333' => [1, 3, '33.33'],
    '2/3 = 66.667' => [2, 3, '66.67'],
    '1/16 = 6.25' => [1, 16, '6.25'],
    '1/1600 = 0.0625 rounds up' => [1, 1600, '0.06'],
    '1/800 = 0.125 rounds up' => [1, 800, '0.13'],
    'all correct' => [40, 40, '100.00'],
    'none correct' => [0, 40, '0.00'],
]);

it('rates an empty set as 0.00', function () {
    expect((new GeneralRatingV1)->percent(0, 0))->toBe('0.00');
});

it('is version v1', function () {
    expect(GeneralRatingV1::VERSION)->toBe('v1');
});
