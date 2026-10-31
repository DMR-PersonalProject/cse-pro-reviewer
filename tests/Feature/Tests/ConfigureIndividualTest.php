<?php

use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Livewire\Tests\ConfigureIndividual;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->subtest = Subtest::factory()->create(['code' => 'numerical', 'position' => 1]);
    $this->topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('requires sign-in', function () {
    auth()->logout();

    $this->get('/practice')->assertRedirect('/start');
});

it('renders at /practice', function () {
    $this->get('/practice')->assertOk()->assertSeeLivewire(ConfigureIndividual::class);
});

it('previews 23 min for 20 items and the available count', function () {
    Question::factory()->public()->published()->count(30)->create(['topic_id' => $this->topic->id]);

    livewire(ConfigureIndividual::class)
        ->assertSet('subtestId', $this->subtest->id)
        ->assertSee(__('Time limit: :minutes min', ['minutes' => 23]))
        ->assertSee(trans_choice(':count item available|:count items available', 30));
});

it('shows no duration for study and untimed', function (AttemptTiming $timing) {
    livewire(ConfigureIndividual::class)
        ->set('timing', $timing->value)
        ->assertDontSee(__('Time limit: :minutes min', ['minutes' => 23]))
        ->assertSee(__('No time limit'));
})->with([AttemptTiming::Study, AttemptTiming::Untimed]);

it('starts a test and redirects to the runner', function () {
    Question::factory()->public()->published()->count(30)->create(['topic_id' => $this->topic->id]);

    $component = livewire(ConfigureIndividual::class)->set('count', 20)->call('start');

    $attempt = Attempt::sole();
    $component->assertRedirect('/attempts/'.$attempt->id);
    expect($attempt->item_count)->toBe(20)->and($attempt->timing)->toBe(AttemptTiming::Timed);
});

it('offers a shorter test when the count is above what is available', function () {
    Question::factory()->public()->published()->count(8)->create(['topic_id' => $this->topic->id]);

    $component = livewire(ConfigureIndividual::class)
        ->set('count', 20)
        ->call('start')
        ->assertSet('shortfallAvailable', 8)
        ->assertSee(trans_choice('Start with :count item|Start with :count items', 8))
        ->assertSee(__('A shorter test takes :minutes min.', ['minutes' => 9]));

    expect(Attempt::count())->toBe(0);

    $component->call('startShorter');
    expect(Attempt::sole()->item_count)->toBe(8)->and(Attempt::sole()->is_partial)->toBeTrue();
});

it('validates the count range', function () {
    livewire(ConfigureIndividual::class)->set('count', 4)->call('start')->assertHasErrors(['count' => 'between']);
    livewire(ConfigureIndividual::class)->set('count', 101)->call('start')->assertHasErrors(['count' => 'between']);
});

it('rejects a topic of another subtest', function () {
    $other = Topic::factory()->create();

    livewire(ConfigureIndividual::class)->set('topicIds', [$other->id])->call('start')->assertHasErrors('topicIds.0');
});

it('offers resume or abandon when a test is in progress', function () {
    $active = Attempt::factory()->create(['user_id' => $this->user->id]);

    livewire(ConfigureIndividual::class)
        ->assertSee(__('You already have a test in progress.'))
        ->assertSee('/attempts/'.$active->id)
        ->call('abandonActive')
        ->assertDontSee(__('You already have a test in progress.'));

    expect($active->fresh()->status)->toBe(AttemptStatus::Abandoned);
});
