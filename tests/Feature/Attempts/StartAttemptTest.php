<?php

use App\Domain\Attempts\AttemptClock;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;

function startIndividual(User $user, Subtest $subtest, int $count, AttemptTiming $timing = AttemptTiming::Timed, array $topicIds = [], bool $allowShortfall = false)
{
    return app(StartAttempt::class)->individual($user, $subtest, $topicIds, $count, $timing, $allowShortfall);
}

beforeEach(function () {
    $this->subtest = Subtest::factory()->create(['code' => 'numerical']);
    $this->topic = Topic::factory()->create(['subtest_id' => $this->subtest->id]);
    $this->user = User::factory()->create();
});

it('AC-5: starts 20 items from the topic with a 23-minute deadline', function () {
    Question::factory()->public()->published()->count(30)->create(['topic_id' => $this->topic->id]);
    $this->freezeSecond();

    $attempt = startIndividual($this->user, $this->subtest, 20, topicIds: [$this->topic->id])->attempt;

    expect($attempt->mode)->toBe(AttemptMode::Individual)
        ->and($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->item_count)->toBe(20)
        ->and($attempt->topic_ids)->toBe([$this->topic->id])
        ->and($attempt->deadline_at->equalTo(now()->addMinutes(23)))->toBeTrue()
        ->and($attempt->answers)->toHaveCount(20)
        ->and($attempt->answers->pluck('question_id')->unique())->toHaveCount(20)
        ->and($attempt->answers->pluck('position')->all())->toBe(range(1, 20))
        ->and($attempt->answers->pluck('topic_id')->unique()->all())->toBe([$this->topic->id]);
});

it('gives untimed and study attempts no deadline', function (AttemptTiming $timing) {
    Question::factory()->public()->published()->count(5)->create(['topic_id' => $this->topic->id]);

    expect(startIndividual($this->user, $this->subtest, 5, $timing)->attempt->deadline_at)->toBeNull();
})->with([AttemptTiming::Untimed, AttemptTiming::Study]);

it('refuses a second start while one is in progress, offering resume or abandon', function () {
    Question::factory()->public()->published()->count(10)->create(['topic_id' => $this->topic->id]);
    $first = startIndividual($this->user, $this->subtest, 5)->attempt;

    $second = startIndividual($this->user, $this->subtest, 5);

    expect($second->attempt)->toBeNull()
        ->and($second->active->id)->toBe($first->id)
        ->and(Attempt::count())->toBe(1);
});

it('starts again once the previous attempt is no longer in progress', function () {
    Question::factory()->public()->published()->count(10)->create(['topic_id' => $this->topic->id]);
    startIndividual($this->user, $this->subtest, 5)->attempt->update(['status' => AttemptStatus::Abandoned]);

    expect(startIndividual($this->user, $this->subtest, 5)->attempt)->not->toBeNull();
});

it('FR-C2: keeps the settings it was started with', function () {
    Question::factory()->public()->published()->count(10)->create(['topic_id' => $this->topic->id]);
    $settings = app(Settings::class);
    $settings->set(SettingKey::AutosaveGraceSeconds, 10);
    $this->freezeSecond();
    $attempt = startIndividual($this->user, $this->subtest, 10)->attempt;

    $settings->set(SettingKey::AutosaveGraceSeconds, 60);
    $settings->set(SettingKey::PassingGrade, 50);
    $settings->set(SettingKey::TotalMinutes, 600);
    $this->travelTo($attempt->deadline_at->copy()->addSeconds(11));
    $attempt->refresh();

    expect($attempt->config)->toMatchArray(['grace_seconds' => 10, 'passing_grade' => 80.0, 'total_minutes' => 190])
        ->and($attempt->config['item_split'])->toBe(['verbal' => 50, 'numerical' => 40, 'analytical' => 40, 'general_info' => 40])
        ->and(app(AttemptClock::class)->isExpired($attempt))->toBeTrue();
});

it('is standard only when full length, unfiltered and timed', function () {
    Question::factory()->public()->published()->count(45)->create(['topic_id' => $this->topic->id]);
    $start = function (int $count, AttemptTiming $timing = AttemptTiming::Timed, array $topicIds = []) {
        $attempt = startIndividual($this->user, $this->subtest, $count, $timing, $topicIds)->attempt;
        $attempt->update(['status' => AttemptStatus::Abandoned]);

        return $attempt->is_standard;
    };

    expect($start(40))->toBeTrue()
        ->and($start(39))->toBeFalse()
        ->and($start(40, AttemptTiming::Untimed))->toBeFalse()
        ->and($start(40, AttemptTiming::Study))->toBeFalse()
        ->and($start(40, topicIds: [$this->topic->id]))->toBeFalse();
});

it('reports a shortfall without starting, and starts a partial test when allowed', function () {
    Question::factory()->public()->published()->count(12)->create(['topic_id' => $this->topic->id]);

    $refused = startIndividual($this->user, $this->subtest, 20);
    $partial = startIndividual($this->user, $this->subtest, 20, allowShortfall: true)->attempt;

    expect($refused->attempt)->toBeNull()
        ->and($refused->available)->toBe(12)
        ->and($partial->is_partial)->toBeTrue()
        ->and($partial->item_count)->toBe(12)
        ->and($partial->answers)->toHaveCount(12);
});

it('refuses to start when no items are available', function () {
    $result = startIndividual($this->user, $this->subtest, 5, allowShortfall: true);

    expect($result->attempt)->toBeNull()->and($result->available)->toBe(0)->and(Attempt::count())->toBe(0);
});

it('flags private items and groups passage items by passage key', function () {
    $admin = User::factory()->admin()->create();
    $passage = Passage::factory()->create(['subtest_id' => $this->subtest->id, 'topic_id' => $this->topic->id]);
    foreach ([1, 2] as $position) {
        Question::factory()->inPassage($passage, $position)->published()->create();
    }

    $attempt = startIndividual($admin, $this->subtest, 2)->attempt;

    expect($attempt->has_private_items)->toBeTrue()
        ->and($attempt->answers->pluck('passage_key')->unique()->all())->toBe(['p'.$passage->id]);
});

it('stores answer rows with snapshots and no answer yet', function () {
    Question::factory()->public()->published()->count(5)->create(['topic_id' => $this->topic->id]);

    $attempt = startIndividual($this->user, $this->subtest, 5)->attempt;
    $answer = $attempt->answers->first();

    expect($attempt->has_private_items)->toBeFalse()
        ->and($attempt->is_partial)->toBeFalse()
        ->and($answer->snapshot['stem'])->toBe(Question::find($answer->question_id)->stem)
        ->and($answer->correct_key)->toBeIn(['A', 'B', 'C', 'D', 'E'])
        ->and($answer->selected_key)->toBeNull()
        ->and($answer->subtest_id)->toBe($this->subtest->id);
});
