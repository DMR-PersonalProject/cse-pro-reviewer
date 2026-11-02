<?php

/**
 * M7-01: reads the design tokens from app.css and checks WCAG AA contrast in both themes.
 * Text pairs need 4.5:1; UI parts (control borders, focus ring, accent buttons) need 3:1.
 */
function cseTokens(string $selector): array
{
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');
    preg_match('/^'.preg_quote($selector, '/').'\s*\{(.*?)\}/ms', $css, $block);
    preg_match_all('/--cse-([a-z-]+):\s*(#[0-9a-f]{6});/i', $block[1] ?? '', $m, PREG_SET_ORDER);

    return collect($m)->mapWithKeys(fn ($t) => [$t[1] => $t[2]])->all();
}

function cseLuminance(string $hex): float
{
    $channels = array_map(function ($c) {
        $c = hexdec($c) / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split(ltrim($hex, '#'), 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function cseContrast(string $a, string $b): float
{
    [$hi, $lo] = [max(cseLuminance($a), cseLuminance($b)), min(cseLuminance($a), cseLuminance($b))];

    return ($hi + 0.05) / ($lo + 0.05);
}

$pairs = [
    // [foreground, background, minimum ratio]
    ['ink', 'canvas', 4.5], ['ink', 'surface', 4.5],
    ['muted', 'canvas', 4.5], ['muted', 'surface', 4.5],
    ['danger', 'canvas', 4.5], ['danger', 'surface', 4.5],
    ['accent', 'canvas', 4.5], ['accent', 'surface', 4.5],
    ['on-accent', 'accent', 4.5],
    ['line', 'canvas', 3.0], ['line', 'surface', 3.0],
    ['focus', 'canvas', 3.0], ['focus', 'surface', 3.0],
];

it('defines every token in both themes', function (string $selector) {
    expect(array_keys(cseTokens($selector)))->toContain(
        'canvas', 'surface', 'ink', 'muted', 'line', 'accent', 'on-accent', 'danger', 'focus',
    );
})->with([':root', '.dark']);

it('meets AA contrast', function (string $selector, string $fg, string $bg, float $min) {
    $tokens = cseTokens($selector);

    expect(round(cseContrast($tokens[$fg], $tokens[$bg]), 2))
        ->toBeGreaterThanOrEqual($min, "{$selector} {$fg} on {$bg}");
})->with([':root', '.dark'])->with($pairs);
