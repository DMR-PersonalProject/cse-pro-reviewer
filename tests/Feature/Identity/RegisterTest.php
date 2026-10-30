<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

function registration(array $overrides = []): array
{
    return array_merge([
        'username' => 'juan',
        'ign' => 'JuanPogi',
        'full_name' => 'Juan dela Cruz',
        'pin' => '',
        'pin_confirmation' => '',
    ], $overrides);
}

function submitRegistration(array $overrides = [])
{
    $component = livewire(Register::class);

    foreach (registration($overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('register');
}

it('shows the start page with the privacy summary', function () {
    $this->get('/start')
        ->assertOk()
        ->assertSeeLivewire(Register::class)
        ->assertSee(__('your full name and attempt details, visible to you and to admins only.'))
        ->assertSee(__('You appear on the leaderboard only after you set a PIN.'));
});

it('creates a lowercase account, signs in and lands on the dashboard (AC-1)', function () {
    submitRegistration(['username' => 'Juan'])
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::sole();
    expect($user)
        ->username->toBe('juan')
        ->ign->toBe('JuanPogi')
        ->full_name->toBe('Juan dela Cruz')
        ->password->toBeNull()
        ->pin_set_at->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('says the username is taken regardless of case (AC-2)', function () {
    User::factory()->create(['username' => 'juan']);

    submitRegistration(['username' => 'JUAN', 'ign' => 'Someone Else'])
        ->assertHasErrors(['username'])
        ->assertSee(__('Username is taken'));

    expect(User::count())->toBe(1);
    $this->assertGuest();
});

it('says the IGN is taken regardless of case', function () {
    User::factory()->create(['ign' => 'JuanPogi']);

    submitRegistration(['username' => 'pedro', 'ign' => 'JUANPOGI'])
        ->assertHasErrors(['ign'])
        ->assertSee(__('IGN is taken'));
});

it('stores an optional PIN hashed and records when it was set', function () {
    submitRegistration(['pin' => '4321', 'pin_confirmation' => '4321'])
        ->assertHasNoErrors();

    $user = User::sole();
    expect($user->password)->not->toBe('4321')
        ->and(Hash::check('4321', $user->password))->toBeTrue()
        ->and($user->pin_set_at)->not->toBeNull()
        ->and($user->hasPin())->toBeTrue();
});

it('trims spaces around the fields', function () {
    submitRegistration(['username' => '  juan ', 'ign' => ' Juan Pogi ', 'full_name' => ' Juan dela Cruz '])
        ->assertHasNoErrors();

    expect(User::sole())
        ->username->toBe('juan')
        ->ign->toBe('Juan Pogi')
        ->full_name->toBe('Juan dela Cruz');
});

it('accepts Filipino letters, spaces, _ and - in the IGN', function () {
    submitRegistration(['ign' => 'Niño_dela-Cruz 1'])->assertHasNoErrors();
});

it('validates the fields', function (array $overrides, string $field) {
    submitRegistration($overrides)->assertHasErrors([$field]);

    expect(User::count())->toBe(0);
})->with([
    'username too short' => [['username' => 'ab'], 'username'],
    'username too long' => [['username' => str_repeat('a', 21)], 'username'],
    'username with a space' => [['username' => 'juan dc'], 'username'],
    'username with a symbol' => [['username' => 'juan!'], 'username'],
    'username with a dash' => [['username' => 'juan-dc'], 'username'],
    'username missing' => [['username' => ''], 'username'],
    'IGN too short' => [['ign' => 'JP'], 'ign'],
    'IGN too long' => [['ign' => str_repeat('a', 21)], 'ign'],
    'IGN with a symbol' => [['ign' => 'Juan@Pogi'], 'ign'],
    'IGN of only spaces' => [['ign' => '    '], 'ign'],
    'IGN missing' => [['ign' => ''], 'ign'],
    'full name too short' => [['full_name' => 'J'], 'full_name'],
    'full name too long' => [['full_name' => str_repeat('a', 101)], 'full_name'],
    'full name missing' => [['full_name' => ''], 'full_name'],
    'PIN too short' => [['pin' => '123', 'pin_confirmation' => '123'], 'pin'],
    'PIN too long' => [['pin' => '1234567', 'pin_confirmation' => '1234567'], 'pin'],
    'PIN with letters' => [['pin' => '12ab', 'pin_confirmation' => '12ab'], 'pin'],
    'PIN not confirmed' => [['pin' => '1234', 'pin_confirmation' => '1243'], 'pin'],
]);

it('sends signed-in users away from the start page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/start')
        ->assertRedirect(route('dashboard'));
});
