<?php

use App\Domain\Attempts\StartAttempt;
use App\Enums\AttemptTiming;
use App\Livewire\Attempt\Runner;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use function Pest\Livewire\livewire;

/**
 * A default-split (50/40/40/40) timed mock with marked explanations, so leaks are easy to spot.
 */
function startNavigatorMock(User $user): Attempt
{
    $source = Source::factory()->public()->create();

    foreach (['verbal' => 50, 'numerical' => 40, 'analytical' => 40, 'general_info' => 40] as $code => $count) {
        $topic = Subtest::query()->where('code', $code)->first()->topics()->first();
        Question::factory()->published()->count($count)
            ->state(new Sequence(fn (Sequence $sequence) => ['explanation' => 'Explanation-'.$code.'-'.$sequence->index]))
            ->create(['topic_id' => $topic->id, 'source_id' => $source->id]);
    }

    return app(StartAttempt::class)->mock($user, AttemptTiming::Timed)->attempt;
}

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->attempt = startNavigatorMock($this->user);
});

it('M5-04: groups 170 navigator entries by subtest in order, with counts', function () {
    $component = livewire(Runner::class, ['attempt' => $this->attempt])
        ->call('select', 'A')
        ->call('toggleFlag');

    $groups = collect($component->instance()->navigatorGroups);

    expect($component->instance()->navigator)->toHaveCount(170)
        ->and($groups->pluck('name')->all())->toBe(['Verbal Ability', 'Numerical Ability', 'Analytical Ability', 'General Information'])
        ->and($groups->pluck('total')->all())->toBe([50, 40, 40, 40])
        ->and($groups->pluck('answered')->all())->toBe([1, 0, 0, 0])
        ->and($groups->pluck('flagged')->all())->toBe([1, 0, 0, 0])
        ->and($groups[1]['entries'][0]['position'])->toBe(51);

    $component->assertSee('Numerical Ability')->assertSee('1 / 50 answered');
});

it('M5-04: goTo jumps across subtests and back', function () {
    $component = livewire(Runner::class, ['attempt' => $this->attempt])->call('goTo', 170);

    expect($component->get('position'))->toBe(170)
        ->and($component->instance()->item->subtest_id)->toBe(Subtest::query()->where('code', 'general_info')->value('id'));

    $component->call('goTo', 51);

    expect($component->get('position'))->toBe(51)
        ->and($this->attempt->fresh()->current_position)->toBe(51);
});

it('AC-10a: the mock navigator payload has no answer data', function () {
    $component = livewire(Runner::class, ['attempt' => $this->attempt]);

    foreach ([1, 50, 51, 120, 170] as $position) {
        $component->call('goTo', $position);
        $payload = $component->html().json_encode($component->snapshot);

        expect($payload)->not->toContain('Explanation-')
            ->not->toContain('correct_key')
            ->not->toContain(__('Correct answer'));
    }
});
