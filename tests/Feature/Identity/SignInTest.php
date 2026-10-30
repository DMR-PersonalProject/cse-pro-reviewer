<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Livewire\Auth\SignIn;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

function signIn(string $username, string $pin = '')
{
    return livewire(SignIn::class)
        ->set('username', $username)
        ->set('pin', $pin)
        ->call('signIn');
}

it('shows the sign-in page to guests', function () {
    $this->get('/login')->assertOk()->assertSeeLivewire(SignIn::class);
});

it('signs in a user with the right PIN', function () {
    $user = User::factory()->withPin('2468')->create(['username' => 'juan']);

    signIn('Juan', '2468')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('signs in a user without a PIN with the username only', function () {
    $user = User::factory()->create(['username' => 'maria']);

    signIn('maria')->assertHasNoErrors()->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('refuses a missing or wrong PIN', function (string $pin) {
    User::factory()->withPin('2468')->create(['username' => 'juan']);

    signIn('juan', $pin)
        ->assertHasErrors(['username'])
        ->assertSee(__('Username or PIN is incorrect.'));

    $this->assertGuest();
})->with(['no PIN' => '', 'wrong PIN' => '1111']);

it('refuses an unknown username with the same message', function () {
    signIn('nobody')->assertSee(__('Username or PIN is incorrect.'));

    $this->assertGuest();
});

it('throttles the 6th failure within the window by default (AC-3)', function () {
    User::factory()->withPin('2468')->create(['username' => 'juan']);

    foreach (range(1, 5) as $attempt) {
        signIn('juan', '0000')->assertSee(__('Username or PIN is incorrect.'));
    }

    // Even the right PIN is refused while throttled.
    signIn('juan', '2468')
        ->assertHasErrors(['username'])
        ->assertSee(__('Too many sign-in attempts.'), escape: false);

    $this->assertGuest();
});

it('throttles the 4th failure after an admin sets the limit to 3 (AC-3)', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 3);
    User::factory()->withPin('2468')->create(['username' => 'juan']);

    foreach (range(1, 3) as $attempt) {
        signIn('juan', '0000')->assertSee(__('Username or PIN is incorrect.'));
    }

    signIn('juan', '0000')->assertSee(__('Too many sign-in attempts.'), escape: false);
});

it('throttles per username, ignoring case', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 1);
    User::factory()->withPin('2468')->create(['username' => 'juan']);
    User::factory()->withPin('1357')->create(['username' => 'pedro']);

    signIn('juan', '0000');

    signIn('JUAN', '2468')->assertSee(__('Too many sign-in attempts.'), escape: false);
    signIn('pedro', '1357')->assertHasNoErrors();
});

it('uses the window from settings', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 1);
    app(Settings::class)->set(SettingKey::LockoutWindowMinutes, 2);
    User::factory()->withPin('2468')->create(['username' => 'juan']);

    signIn('juan', '0000');
    $this->travel(119)->seconds();
    signIn('juan', '2468')->assertSee(__('Too many sign-in attempts.'), escape: false);

    $this->travel(2)->seconds();
    signIn('juan', '2468')->assertHasNoErrors();
});

it('clears the failure count after a successful sign-in', function () {
    User::factory()->withPin('2468')->create(['username' => 'juan']);

    signIn('juan', '0000');
    signIn('juan', '2468')->assertHasNoErrors();

    expect(RateLimiter::attempts('login:juan|127.0.0.1'))->toBe(0);
});

it('refuses admins on this path', function () {
    // The password would match as a PIN, so only the role check can refuse it.
    User::factory()->admin()->create(['username' => 'boss', 'password' => '9876']);

    signIn('boss', '9876')->assertSee(__('Username or PIN is incorrect.'));

    $this->assertGuest();
});

it('refuses an inactive user even with the right PIN', function () {
    User::factory()->inactive()->withPin('2468')->create(['username' => 'juan']);

    signIn('juan', '2468')
        ->assertHasErrors(['username'])
        ->assertSee(__('This account has been deactivated. Ask an admin for help.'));

    $this->assertGuest();
});

it('regenerates the session on sign-in', function () {
    User::factory()->create(['username' => 'maria']);
    $before = session()->getId();

    signIn('maria');

    expect(session()->getId())->not->toBe($before);
});

it('sends signed-in users away from the sign-in page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect(route('dashboard'));
});
