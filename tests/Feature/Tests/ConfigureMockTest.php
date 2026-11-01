<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Livewire\Tests\ConfigureMock;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;

use function Pest\Livewire\livewire;

/**
 * Split 2 per subtest; `$counts` published items per subtest code in its first topic.
 *
 * @param  array<string, int>  $counts
 */
function mockSetupBank(array $counts): void
{
    foreach (SettingKey::itemSplit() as $key) {
        app(Settings::class)->set($key, 2);
    }

    $source = Source::factory()->public()->create();

    foreach ($counts as $code => $count) {
        $topic = Subtest::query()->where('code', $code)->first()->topics()->first();
        Question::factory()->published()->count($count)->create(['topic_id' => $topic->id, 'source_id' => $source->id]);
    }
}

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('requires sign-in', function () {
    auth()->logout();

    $this->get('/mock')->assertRedirect('/start');
});

it('renders at /mock with the split marked unverified and what is available', function () {
    mockSetupBank(['verbal' => 1]);

    $this->get('/mock')->assertOk()->assertSeeLivewire(ConfigureMock::class);

    livewire(ConfigureMock::class)
        ->assertSee(__('Unverified'))
        ->assertSee('Verbal Ability')
        ->assertSeeInOrder(['Verbal Ability', '2', '1', 'Numerical Ability'])
        ->assertSee(__('One clock of :minutes min for the whole exam. Subtests have no separate time limits.', ['minutes' => 190]));
});

it('starts a timed mock with one 190-minute deadline and no subtest limits', function () {
    mockSetupBank(['verbal' => 2, 'numerical' => 2, 'analytical' => 2, 'general_info' => 2]);

    livewire(ConfigureMock::class)->call('start')->assertRedirect();

    $attempt = Attempt::sole();

    expect($attempt->mode)->toBe(AttemptMode::Combined)
        ->and((int) round($attempt->started_at->diffInMinutes($attempt->deadline_at)))->toBe(190)
        ->and($attempt->config)->not->toHaveKey('subtest_minutes')
        ->and(collect($attempt->config)->keys()->filter(fn ($key) => str_contains($key, 'limit')))->toBeEmpty();
});

it('starts an untimed mock with no deadline', function () {
    mockSetupBank(['verbal' => 2, 'numerical' => 2, 'analytical' => 2, 'general_info' => 2]);

    livewire(ConfigureMock::class)->set('timing', 'untimed')->call('start')->assertRedirect();

    expect(Attempt::sole()->deadline_at)->toBeNull();
});

it('refuses study timing', function () {
    livewire(ConfigureMock::class)->set('timing', 'study')->call('start')->assertHasErrors('timing');

    expect(Attempt::count())->toBe(0);
});

it('lists the counts per subtest on a shortfall and starts a partial mock on request', function () {
    mockSetupBank(['verbal' => 2, 'numerical' => 1]);

    $component = livewire(ConfigureMock::class)->call('start')
        ->assertNoRedirect()
        ->assertSee(__(':subtest: :available of :requested items', ['subtest' => 'Numerical Ability', 'available' => 1, 'requested' => 2]))
        ->assertSee(__(':subtest: :available of :requested items', ['subtest' => 'General Information', 'available' => 0, 'requested' => 2]))
        ->assertSee(__('A partial mock is labeled "partial" and is never ranked.'));

    expect(Attempt::count())->toBe(0);

    $component->call('startPartial')->assertRedirect();

    expect(Attempt::sole())->is_partial->toBeTrue()->is_standard->toBeFalse()->item_count->toBe(3);
});

it('offers resume or abandon when a test is in progress', function () {
    $active = Attempt::factory()->create(['user_id' => $this->user->id, 'status' => AttemptStatus::InProgress]);

    livewire(ConfigureMock::class)
        ->assertSee(__('You already have a test in progress.'))
        ->call('abandonActive');

    expect($active->fresh()->status)->toBe(AttemptStatus::Abandoned);
});
