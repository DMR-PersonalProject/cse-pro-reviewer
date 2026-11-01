<?php

use App\Domain\Assembly\ItemPool;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\QuestionStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;

function poolIds(User $user, Topic $topic, array $topicIds = []): array
{
    return app(ItemPool::class)->build($user, [$topic->subtest_id], $topicIds)->all()->pluck('id')->all();
}

function answeredIn(User $user, Question $question, string $at): void
{
    $attempt = Attempt::factory()->submitted()->create(['user_id' => $user->id, 'started_at' => $at]);
    AttemptAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'selected_key' => 'A',
        'answered_at' => $at,
    ]);
}

beforeEach(function () {
    $this->topic = Topic::factory()->create();
    $this->user = User::factory()->create();
});

it('excludes draft, needs-review and archived items', function () {
    $published = Question::factory()->public()->published()->create(['topic_id' => $this->topic->id]);
    foreach ([QuestionStatus::Draft, QuestionStatus::NeedsReview, QuestionStatus::Archived] as $status) {
        Question::factory()->public()->create(['topic_id' => $this->topic->id, 'status' => $status]);
    }

    expect(poolIds($this->user, $this->topic))->toBe([$published->id]);
});

it('shows private items to an admin but not to a user', function () {
    $public = Question::factory()->public()->published()->create(['topic_id' => $this->topic->id]);
    $private = Question::factory()->private()->published()->create(['topic_id' => $this->topic->id]);

    expect(poolIds($this->user, $this->topic))->toBe([$public->id])
        ->and(poolIds(User::factory()->admin()->create(), $this->topic))->toBe([$public->id, $private->id]);
});

it('hides a public item from a user when its passage source is private', function () {
    $passage = Passage::factory()->create(['topic_id' => $this->topic->id, 'subtest_id' => $this->topic->subtest_id]);
    Question::factory()->inPassage($passage)->published()->create(['source_id' => Source::factory()->public()]);

    expect(poolIds($this->user, $this->topic))->toBe([]);
});

it('filters by subtest and topic', function () {
    $other = Topic::factory()->create(['subtest_id' => $this->topic->subtest_id]);
    $inTopic = Question::factory()->public()->published()->create(['topic_id' => $this->topic->id]);
    $inOther = Question::factory()->public()->published()->create(['topic_id' => $other->id]);
    Question::factory()->public()->published()->create();

    expect(poolIds($this->user, $this->topic))->toBe([$inTopic->id, $inOther->id])
        ->and(poolIds($this->user, $this->topic, [$other->id]))->toBe([$inOther->id]);
});

it('puts items answered in the last N attempts after fresh ones, oldest first', function () {
    [$a, $b, $c] = Question::factory()->public()->published()->count(3)->create(['topic_id' => $this->topic->id]);
    answeredIn($this->user, $a, '2026-10-02 09:00:00');
    answeredIn($this->user, $b, '2026-10-01 09:00:00');

    $pool = app(ItemPool::class)->build($this->user, [$this->topic->subtest_id]);

    expect($pool->fresh->pluck('id')->all())->toBe([$c->id])
        ->and($pool->seen->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and(poolIds($this->user, $this->topic))->toBe([$c->id, $b->id, $a->id]);
});

it('counts only the last N attempts and only answered items', function () {
    [$old, $recent, $skipped] = Question::factory()->public()->published()->count(3)->create(['topic_id' => $this->topic->id]);
    app(Settings::class)->set(SettingKey::RepeatWindowAttempts, 1);
    answeredIn($this->user, $old, '2026-10-01 09:00:00');
    answeredIn($this->user, $recent, '2026-10-02 09:00:00');
    AttemptAnswer::factory()->create([
        'attempt_id' => $this->user->attempts()->latest('started_at')->first()->id,
        'question_id' => $skipped->id,
        'position' => 2,
    ]);

    $pool = app(ItemPool::class)->build($this->user, [$this->topic->subtest_id]);

    expect($pool->seen->pluck('id')->all())->toBe([$recent->id])
        ->and($pool->fresh->pluck('id')->all())->toBe([$old->id, $skipped->id]);
});

it('treats every item as fresh when the repeat window is 0', function () {
    $question = Question::factory()->public()->published()->create(['topic_id' => $this->topic->id]);
    answeredIn($this->user, $question, '2026-10-01 09:00:00');
    app(Settings::class)->set(SettingKey::RepeatWindowAttempts, 0);

    expect(app(ItemPool::class)->build($this->user, [$this->topic->subtest_id])->seen)->toBeEmpty();
});

it('ignores other users\' attempts', function () {
    $question = Question::factory()->public()->published()->create(['topic_id' => $this->topic->id]);
    answeredIn(User::factory()->create(), $question, '2026-10-01 09:00:00');

    expect(app(ItemPool::class)->build($this->user, [$this->topic->subtest_id])->fresh)->toHaveCount(1);
});
