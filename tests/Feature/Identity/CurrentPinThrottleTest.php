<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

function changePin(string $current, string $new = '222222')
{
    return livewire(Edit::class)
        ->set('change_current_pin', $current)
        ->set('pin', $new)
        ->set('pin_confirmation', $new)
        ->call('savePin');
}

function removePin(string $current)
{
    return livewire(Edit::class)
        ->set('current_pin', $current)
        ->call('removePin');
}

it('locks the change form after repeated wrong current PINs (#19)', function () {
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    foreach (range(1, 5) as $attempt) {
        changePin('0000')->assertSee(__('That isn’t your current PIN.'), escape: false);
    }

    // Even the right PIN is refused while locked out.
    changePin('1111')
        ->assertHasErrors(['change_current_pin'])
        ->assertSee(__('Too many wrong PINs.'), escape: false);

    expect(Hash::check('1111', $user->refresh()->password))->toBeTrue();
});

it('locks the remove form after repeated wrong current PINs (#19)', function () {
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    foreach (range(1, 5) as $attempt) {
        removePin('0000')->assertSee(__('That isn’t your current PIN.'), escape: false);
    }

    removePin('1111')
        ->assertHasErrors(['current_pin'])
        ->assertSee(__('Too many wrong PINs.'), escape: false);

    expect($user->refresh()->hasPin())->toBeTrue();
});

it('shares one counter across both forms', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 2);
    $this->actingAs(User::factory()->withPin('1111')->create());

    changePin('0000');
    removePin('0000');

    changePin('1111')->assertSee(__('Too many wrong PINs.'), escape: false);
    removePin('1111')->assertSee(__('Too many wrong PINs.'), escape: false);
});

it('throttles per user', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 1);

    $this->actingAs(User::factory()->withPin('1111')->create());
    removePin('0000');

    $this->actingAs(User::factory()->withPin('1111')->create());
    removePin('1111')->assertHasNoErrors();
});

it('accepts the right PIN after the lockout window', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 1);
    app(Settings::class)->set(SettingKey::LockoutWindowMinutes, 2);
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    changePin('0000');
    $this->travel(119)->seconds();
    changePin('1111')->assertSee(__('Too many wrong PINs.'), escape: false);

    $this->travel(2)->seconds();
    changePin('1111')->assertHasNoErrors();

    expect(Hash::check('222222', $user->refresh()->password))->toBeTrue();
});

it('clears the counter after a right current PIN', function () {
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    changePin('0000');
    changePin('1111')->assertHasNoErrors();

    expect(RateLimiter::attempts('pin-check:'.$user->id))->toBe(0);
});

it('does not count setting a first PIN', function () {
    app(Settings::class)->set(SettingKey::LockoutMaxAttempts, 1);
    $user = User::factory()->create();
    $this->actingAs($user);

    changePin('', '1357')->assertHasNoErrors();

    expect(RateLimiter::attempts('pin-check:'.$user->id))->toBe(0);
});
