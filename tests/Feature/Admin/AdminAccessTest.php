<?php

use App\Models\User;
use Filament\Auth\Pages\Login;

use function Pest\Livewire\livewire;

it('gives a user-role account a 403 at /admin (AC-4)', function () {
    $this->actingAs(User::factory()->withPin()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('sends guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an active admin reach the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('signs an admin in with email and password', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'correct-horse']);

    livewire(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'correct-horse'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
});

it('refuses the admin login for non-admins and inactive admins', function (Closure $makeUser) {
    $makeUser();

    livewire(Login::class)
        ->fillForm(['email' => 'someone@example.com', 'password' => 'correct-horse'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
})->with([
    'user role with an email' => fn () => fn () => User::factory()->create(['email' => 'someone@example.com', 'password' => 'correct-horse']),
    'inactive admin' => fn () => fn () => User::factory()->admin()->inactive()->create(['email' => 'someone@example.com', 'password' => 'correct-horse']),
]);

it('logs out an inactive admin who still has a session', function () {
    $this->actingAs(User::factory()->admin()->inactive()->create())
        ->get('/admin')
        ->assertRedirect('/admin/login');

    $this->assertGuest();
});

it('logs out a user deactivated mid-session on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();

    $this->get(route('login'))->assertSee(__('This account has been deactivated. Ask an admin for help.'));
});
