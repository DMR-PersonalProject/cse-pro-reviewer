<?php

use App\Enums\AttemptMode;
use App\Livewire\Leaderboard\Index;
use App\Models\Attempt;
use App\Models\AttemptResult;
use App\Models\AttemptSubtestScore;
use App\Models\Subtest;
use App\Models\User;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $attributes
 */
function pageMock(User $user, string $gr, array $attributes = []): void
{
    $attempt = Attempt::factory()->submitted()->create([
        'user_id' => $user->id,
        'mode' => AttemptMode::Combined,
        'subtest_id' => null,
        'is_standard' => true,
        ...$attributes,
    ]);
    AttemptResult::factory()->create(['attempt_id' => $attempt->id, 'general_rating' => $gr]);
}

it('lets a guest view the board', function () {
    pageMock(User::factory()->withPin()->create(['ign' => 'Ana']), '88.25');

    $this->get(route('leaderboard'))
        ->assertOk()
        ->assertSee('Ana')
        ->assertSee('88.25%')
        ->assertSee('10:00');
});

it('AC-19: the HTML never contains a full name', function () {
    pageMock(User::factory()->withPin()->create(['ign' => 'Ana', 'full_name' => 'Ana Secret Santos']), '88.00');

    $this->get(route('leaderboard'))->assertOk()->assertSee('Ana')->assertDontSee('Ana Secret Santos');
});

it('shows an empty state', function () {
    $this->get(route('leaderboard'))->assertOk()->assertSee(__('No ranked attempts yet for this board and period.'));
});

it('switches board and period through the URL', function () {
    $verbal = Subtest::factory()->create(['code' => 'verbal', 'name' => 'Verbal Ability']);
    $mockUser = User::factory()->withPin()->create(['ign' => 'MockPlayer']);
    $oldUser = User::factory()->withPin()->create(['ign' => 'OldPlayer']);
    $verbalUser = User::factory()->withPin()->create(['ign' => 'VerbalPlayer']);
    pageMock($mockUser, '90.00');
    pageMock($oldUser, '95.00', ['finalized_at' => now()->subWeeks(3)]);
    $attempt = Attempt::factory()->submitted()->create(['user_id' => $verbalUser->id, 'subtest_id' => $verbal->id, 'is_standard' => true]);
    AttemptSubtestScore::factory()->create(['attempt_id' => $attempt->id, 'subtest_id' => $verbal->id, 'percent' => '70.00']);

    Livewire::test(Index::class)
        ->assertSee('MockPlayer')->assertSee('OldPlayer')->assertDontSee('VerbalPlayer')
        ->set('period', 'week')
        ->assertSee('MockPlayer')->assertDontSee('OldPlayer')
        ->set('board', 'verbal')
        ->assertSee('VerbalPlayer')->assertDontSee('MockPlayer');

    Livewire::withQueryParams(['board' => 'verbal', 'period' => 'all'])
        ->test(Index::class)
        ->assertSee('VerbalPlayer')->assertDontSee('MockPlayer');
});

it('falls back to the all-time mock board for unknown values', function () {
    pageMock(User::factory()->withPin()->create(['ign' => 'Ana']), '88.00');

    $this->get(route('leaderboard', ['board' => 'nope', 'period' => 'decade']))->assertOk()->assertSee('Ana');
});
