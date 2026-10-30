<?php

use App\Livewire\Dashboard;
use App\Models\User;

it('redirects guests from / to /start', function () {
    $this->get('/')->assertRedirect('/start');
});

it('shows the dashboard to a signed-in user', function () {
    $this->actingAs(User::factory()->create(['ign' => 'JuanPogi']))
        ->get('/')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('JuanPogi');
});

it('nudges users without a PIN only', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee(__('Set a PIN to protect your username'));

    $this->actingAs(User::factory()->withPin()->create())
        ->get('/')
        ->assertDontSee(__('Set a PIN to protect your username'));

    $this->actingAs(User::factory()->admin()->create())
        ->get('/')
        ->assertDontSee(__('Set a PIN to protect your username'));
});

it('links the PIN nudge to the profile', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee('href="'.route('profile').'"', escape: false);
});

it('lists what is stored and what is public on /privacy, for everyone', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee(__('Stored:'))
        ->assertSee(__('username, IGN, full name, an optional PIN (hashed, never readable), your attempts and answers, and timestamps.'))
        ->assertSee(__('Public:'))
        ->assertSee(__('your IGN, leaderboard scores, and the time used on eligible attempts.'))
        ->assertSee(__('Private:'));

    $this->actingAs(User::factory()->create())->get('/privacy')->assertOk();
});

it('links to the privacy page from every page', function () {
    $this->get('/start')->assertSee('href="'.route('privacy').'"', escape: false);
});

it('renders 404 in the app layout', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee(__('Page not found'))
        ->assertSee(__('Skip to content'));
});

it('renders 403 in the app layout', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden()
        ->assertSee(__('You don’t have access to this page'))
        ->assertSee(__('Skip to content'));
});
