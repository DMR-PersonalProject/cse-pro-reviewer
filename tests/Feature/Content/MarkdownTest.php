<?php

use App\Domain\Content\MarkdownRenderer;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    $this->markdown = app(MarkdownRenderer::class);
});

it('renders basic Markdown', function () {
    expect($this->markdown->render("**Bold** and *italic*\n\n- one\n- two"))
        ->toContain('<strong>Bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<li>one</li>');
});

it('strips script tags and other raw HTML', function () {
    $html = $this->markdown->render("Hello <script>alert('x')</script> <b onclick=\"steal()\">there</b>\n\n<iframe src=\"https://evil.test\"></iframe>");

    expect($html)
        ->not->toContain('<script')
        ->not->toContain('<b')
        ->not->toContain('onclick')
        ->not->toContain('<iframe')
        ->toContain('Hello');
});

it('removes javascript:, vbscript: and data: links', function (string $href) {
    $html = $this->markdown->render("[click me]({$href})");

    expect($html)->not->toContain($href)->toContain('click me');
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    'vbscript:msgbox(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
]);

it('keeps ordinary links', function () {
    expect($this->markdown->render('[CSC](https://csc.gov.ph)'))->toContain('href="https://csc.gov.ph"');
});

it('renders a Markdown table as a table', function () {
    $html = $this->markdown->render("| Year | Sales |\n|---|---:|\n| 2023 | 120 |\n| 2024 | 150 |");

    expect($html)
        ->toContain('<table>')
        ->toContain('<th>Year</th>')
        ->toContain('<td align="right">150</td>');
});

it('returns an empty string for empty input', function (?string $input) {
    expect($this->markdown->render($input))->toBe('');
})->with([null, '', '   ']);

it('has a Blade component that renders safe Markdown with prose styles', function () {
    $html = Blade::render('<x-markdown :text="$text" class="mt-2" />', ['text' => '**Hi** <script>x()</script>']);

    expect($html)
        ->toContain('<strong>Hi</strong>')
        ->not->toContain('<script')
        ->toContain('prose')
        ->toContain('mt-2');
});
