<?php

use App\Models\User;
use Illuminate\Support\Facades\Vite;

function htmlTag(string $content): string
{
    preg_match('/<html[^>]*>/', $content, $match);

    return $match[0] ?? '';
}

it('renders the dark class on the server when the cookie says dark', function () {
    $html = $this->withUnencryptedCookie('theme', 'dark')->get('/start')->getContent();

    expect(htmlTag($html))->toContain('class="dark"')->toContain('data-theme="dark"');
});

it('renders no dark class for light, system, a missing or an unknown cookie', function (?string $cookie, string $mode) {
    $request = $cookie === null ? $this : $this->withUnencryptedCookie('theme', $cookie);

    $tag = htmlTag($request->get('/start')->getContent());

    expect($tag)->not->toContain('dark"')->toContain('data-theme="'.$mode.'"');
})->with([
    'light' => ['light', 'light'],
    'system' => ['system', 'system'],
    'missing' => [null, 'system'],
    'unknown' => ['purple', 'system'],
]);

it('runs the blocking theme script before any stylesheet', function () {
    // Serve assets from a fake Vite dev server so the stylesheet link is rendered.
    $hot = storage_path('framework/testing/hot');
    @mkdir(dirname($hot), recursive: true);
    file_put_contents($hot, 'http://localhost:5173');
    $this->withVite();
    Vite::useHotFile($hot);

    $html = $this->get('/start')->getContent();
    @unlink($hot);

    $script = strpos($html, 'data-theme-script');
    $stylesheet = strpos($html, 'resources/css/app.css');

    expect($script)->toBeInt()
        ->and($stylesheet)->toBeInt()
        ->and($script)->toBeLessThan($stylesheet)
        ->and($html)->toContain('filament:theme:mode');
});

it('has a theme toggle with an accessible name and pressed state', function () {
    $html = $this->withUnencryptedCookie('theme', 'dark')->get('/start')->getContent();

    expect($html)
        ->toContain('role="group" aria-label="'.__('Theme').'"')
        ->toMatch('/<button[^>]*data-theme-option="dark"[^>]*aria-pressed="true"/')
        ->toMatch('/<button[^>]*data-theme-option="light"[^>]*aria-pressed="false"/')
        ->toMatch('/<button[^>]*data-theme-option="system"[^>]*aria-pressed="false"/');
});

it('has a skip link to the main content', function () {
    $html = $this->get('/start')->getContent();

    expect($html)
        ->toContain('href="#main"')
        ->toContain(__('Skip to content'))
        ->toContain('id="main"')
        ->and(strpos($html, 'href="#main"'))->toBeLessThan(strpos($html, '<header'));
});

it('shows guest links to guests and account links to users', function () {
    $this->get('/start')->assertSee(__('Sign in'))->assertDontSee(__('Sign out'));

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee(__('Profile'))
        ->assertSee(__('Sign out'));
});

it('signs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('start'));

    $this->assertGuest();
});
