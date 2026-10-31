<?php

use App\Domain\Attempts\StartAttempt;
use App\Enums\AttemptTiming;
use App\Livewire\Attempt\Runner;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $subtest = Subtest::factory()->create(['code' => 'numerical']);
    $topic = Topic::factory()->create(['subtest_id' => $subtest->id]);
    Question::factory()->public()->published()->count(20)->create(['topic_id' => $topic->id]);

    $this->start = fn (AttemptTiming $timing) => app(StartAttempt::class)->individual($this->user, $subtest, [], 20, $timing)->attempt;
});

it('AC-6: after a reload with 5 minutes left, the time and answers come from the server', function () {
    $this->freezeSecond();
    $attempt = ($this->start)(AttemptTiming::Timed);
    livewire(Runner::class, ['attempt' => $attempt])->call('goTo', 3)->call('select', 'C');

    $this->travelTo($attempt->deadline_at->subMinutes(5));
    $html = $this->get('/attempts/'.$attempt->id)->assertOk()->getContent();

    expect($html)->toContain('data-remaining-ms="300000"')
        ->toContain('05:00')
        ->toContain(__('Item :position of :total', ['position' => 3, 'total' => 20]))
        ->toMatch('/value="C"\s+checked/');
});

it('has a polite live region and a 60-second resync poll', function () {
    $html = livewire(Runner::class, ['attempt' => ($this->start)(AttemptTiming::Timed)])->html();

    expect($html)->toMatch('/<p[^>]*id="clock-announcer"[^>]*aria-live="polite"/')
        ->toContain('wire:poll.60s')
        ->toContain('x-init="$store.clock.start(');
});

it('shows elapsed time for untimed and study attempts', function (AttemptTiming $timing) {
    $this->freezeSecond();
    $attempt = ($this->start)($timing);
    $this->travel(90)->seconds();

    $html = $this->get('/attempts/'.$attempt->id)->getContent();

    expect($html)->toContain('data-elapsed-ms="90000"')
        ->toContain(__('Elapsed'))
        ->toContain('01:30')
        ->not->toContain('data-remaining-ms');
})->with([AttemptTiming::Untimed, AttemptTiming::Study]);
