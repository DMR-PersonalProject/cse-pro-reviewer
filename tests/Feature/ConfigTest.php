<?php

it('stores times in UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

it('displays times in Manila time', function () {
    expect(config('cse.display_timezone'))->toBe('Asia/Manila');
});
