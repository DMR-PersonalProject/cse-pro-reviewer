<?php

use App\Domain\Identity\SetPin;
use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

it('requires sign-in', function () {
    $this->get('/profile')->assertRedirect('/start');
});

it('shows the username, IGN and full name read-only', function () {
    $user = User::factory()->create(['username' => 'juan', 'ign' => 'JuanPogi', 'full_name' => 'Juan dela Cruz']);

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertSeeLivewire(Edit::class)
        ->assertSee('juan')
        ->assertSee('JuanPogi')
        ->assertSee('Juan dela Cruz');
});

it('shows a persistent notice while there is no PIN (FR-I3)', function () {
    $this->actingAs(User::factory()->create());
    livewire(Edit::class)->assertSee(__('You have no PIN yet.'));

    $this->actingAs(User::factory()->withPin()->create());
    livewire(Edit::class)->assertDontSee(__('You have no PIN yet.'));
});

it('sets a PIN', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(Edit::class)
        ->set('pin', '1357')
        ->set('pin_confirmation', '1357')
        ->call('savePin')
        ->assertHasNoErrors()
        ->assertSet('pin', '')
        ->assertSee(__('PIN saved.'));

    $user->refresh();
    expect($user->hasPin())->toBeTrue()
        ->and(Hash::check('1357', $user->password))->toBeTrue()
        ->and($user->pin_set_at)->not->toBeNull();
});

it('changes a PIN', function () {
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    livewire(Edit::class)
        ->set('pin', '222222')
        ->set('pin_confirmation', '222222')
        ->call('savePin')
        ->assertHasNoErrors();

    expect(Hash::check('222222', $user->refresh()->password))->toBeTrue();
});

it('validates a new PIN', function (string $pin, string $confirmation) {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(Edit::class)
        ->set('pin', $pin)
        ->set('pin_confirmation', $confirmation)
        ->call('savePin')
        ->assertHasErrors(['pin']);

    expect($user->refresh()->hasPin())->toBeFalse();
})->with([
    'missing' => ['', ''],
    'too short' => ['123', '123'],
    'too long' => ['1234567', '1234567'],
    'letters' => ['12ab', '12ab'],
    'not confirmed' => ['1234', '4321'],
]);

it('removes a PIN with the current PIN', function () {
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    livewire(Edit::class)
        ->set('current_pin', '1111')
        ->call('removePin')
        ->assertHasNoErrors()
        ->assertSee(__('PIN removed.'));

    $user->refresh();
    expect($user->password)->toBeNull()
        ->and($user->pin_set_at)->toBeNull();
});

it('refuses to remove a PIN with the wrong PIN', function (string $wrong) {
    $user = User::factory()->withPin('1111')->create();
    $this->actingAs($user);

    livewire(Edit::class)
        ->set('current_pin', $wrong)
        ->call('removePin')
        ->assertHasErrors(['current_pin']);

    expect($user->refresh()->hasPin())->toBeTrue();
})->with(['wrong' => '2222', 'empty' => '']);

it('does not let a user edit the IGN', function () {
    $user = User::factory()->create(['ign' => 'JuanPogi']);
    $this->actingAs($user);

    expect(fn () => livewire(Edit::class)->set('ign', 'Hacker')->call('savePin'))->toThrow(Exception::class);

    expect($user->refresh()->ign)->toBe('JuanPogi');
});

it('never turns an admin password into a PIN', function () {
    $admin = User::factory()->admin()->create();

    app(SetPin::class)->handle($admin, '1234', '1234');
})->throws(AuthorizationException::class);
