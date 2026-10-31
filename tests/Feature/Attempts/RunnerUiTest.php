<?php

use App\Livewire\Attempt\Runner;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;

use function Pest\Livewire\livewire;

/**
 * An in-progress attempt with one item whose snapshot is given.
 *
 * @param  array<string, mixed>  $snapshot
 */
function uiAttempt(User $user, array $snapshot = []): Attempt
{
    $attempt = Attempt::factory()->create(['user_id' => $user->id, 'item_count' => 1]);
    $answer = AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => Question::factory()]);
    $answer->update(['snapshot' => [...$answer->snapshot, ...$snapshot]]);

    return $attempt;
}

function image(string $alt): array
{
    return ['disk' => 'public', 'path' => 'media/chart.png', 'mime' => 'image/png', 'width' => 400, 'height' => 300, 'alt_text' => $alt];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the choices as a labelled radio group', function () {
    $html = livewire(Runner::class, ['attempt' => uiAttempt($this->user)])->html();

    expect($html)->toContain('<fieldset')
        ->toContain('<legend class="sr-only">'.__('Choices for item :position', ['position' => 1]).'</legend>');

    foreach (['A', 'B', 'C', 'D'] as $key) {
        expect($html)->toMatch('/<input type="radio" id="choice-1-'.$key.'"/')
            ->toContain('<label for="choice-1-'.$key.'"');
    }
});

it('keeps the selected choice checked after a reload', function () {
    $attempt = uiAttempt($this->user);
    livewire(Runner::class, ['attempt' => $attempt])->call('select', 'B');

    $html = $this->get('/attempts/'.$attempt->id)->assertOk()->getContent();

    expect($html)->toMatch('/value="B"\s+checked/')->not->toMatch('/value="A"\s+checked/');
});

it('wraps a data table in a labelled scrolling region', function () {
    $attempt = uiAttempt($this->user, ['stem' => "Which year sold most?\n\n| Year | Sales |\n|---|---|\n| 2023 | 120 |"]);

    livewire(Runner::class, ['attempt' => $attempt])
        ->assertSeeHtml('<div role="region" aria-label="'.__('Data table').'" tabindex="0" class="overflow-x-auto"><table>');
});

it('shows images with their alt text', function () {
    $attempt = uiAttempt($this->user, [
        'media' => [image('Bar chart of sales')],
        'passage' => ['body' => 'Read this.', 'media' => [image('Map of the region')]],
    ]);

    livewire(Runner::class, ['attempt' => $attempt])
        ->assertSeeHtml('alt="Bar chart of sales"')
        ->assertSeeHtml('alt="Map of the region"');
});

it('shows a passage in its own labelled region beside the item', function () {
    $attempt = uiAttempt($this->user, ['passage' => ['body' => 'The passage text.', 'media' => []]]);

    livewire(Runner::class, ['attempt' => $attempt])
        ->assertSeeHtml('aria-label="'.__('Passage').'"')
        ->assertSee('The passage text.')
        ->assertSeeHtml('lg:grid-cols-2');
});

it('shows the save indicator and the navigator', function () {
    livewire(Runner::class, ['attempt' => uiAttempt($this->user)])
        ->assertSee(__('Saved'))
        ->assertSeeHtml('aria-label="'.__('Items').'"')
        ->assertSeeHtml('aria-current="step"');
});

it('AC-12: pressing B saves the second shown choice', function () {
    $attempt = uiAttempt($this->user);
    $second = $attempt->answers()->first()->snapshot['choices'][1]['key'];

    $html = livewire(Runner::class, ['attempt' => $attempt])->html();
    // The keyboard store clicks the second radio inside #choices, which sends its value.
    expect($html)->toContain('id="choices"')
        ->toMatch('/<input type="radio" id="choice-1-'.$second.'"[^>]*value="'.$second.'"/');

    livewire(Runner::class, ['attempt' => $attempt])->call('select', $second);
    expect($attempt->answers()->first()->selected_key)->toBe('B');
});

it('binds the keyboard store and lists every key in the help dialog', function () {
    $html = livewire(Runner::class, ['attempt' => uiAttempt($this->user)])->html();

    expect($html)->toContain('x-init="$store.keyboard.bind($wire)"')
        ->toContain('<dialog id="keyboard-help"');

    foreach (['1', '5', 'A', 'E', '←', '→', 'F', 'N', '?'] as $key) {
        expect($html)->toContain('<kbd>'.$key.'</kbd>');
    }
});
