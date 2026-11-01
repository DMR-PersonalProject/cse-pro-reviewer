<?php

use App\Domain\Assembly\TestAssembler;
use App\Domain\Attempts\StartAttempt;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Question;
use App\Models\Source;
use App\Models\Subtest;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;

/**
 * Gives every topic of every subtest the same number of published public items.
 */
function fullBank(int $perTopic): void
{
    $source = Source::factory()->public()->create();

    Topic::query()->each(fn (Topic $topic) => Question::factory()->published()->count($perTopic)
        ->create(['topic_id' => $topic->id, 'source_id' => $source->id]));
}

beforeEach(function () {
    $this->seed(TaxonomySeeder::class);
    $this->user = User::factory()->create();
});

it('AC-6: assembles 170 items split 50/40/40/40 in V, N, A, GI order', function () {
    fullBank(20);

    $result = app(TestAssembler::class)->assembleMock($this->user, app(Settings::class)->itemSplit(), 42);
    $codes = Subtest::query()->pluck('code', 'id');
    $order = $result->questions()->map(fn (Question $question) => $codes[$question->subtest_id]);

    expect($result->count())->toBe(170)
        ->and($result->shortfall())->toBe(0)
        ->and($order->countBy()->all())->toBe(['verbal' => 50, 'numerical' => 40, 'analytical' => 40, 'general_info' => 40])
        ->and($order->unique()->values()->all())->toBe(['verbal', 'numerical', 'analytical', 'general_info'])
        ->and($result->questions()->pluck('id')->unique())->toHaveCount(170);
});

it('balances topics within each subtest to within one item', function () {
    fullBank(20);

    $result = app(TestAssembler::class)->assembleMock($this->user, app(Settings::class)->itemSplit(), 7);

    Subtest::query()->with('topics')->get()->each(function (Subtest $subtest) use ($result) {
        $counts = $subtest->topics->map(fn (Topic $topic) => $result->questions()->where('topic_id', $topic->id)->count());

        expect($counts->max() - $counts->min())->toBeLessThanOrEqual(1);
    });
});

it('fills a short topic from the other topics of the same subtest', function () {
    fullBank(20);
    $numerical = Subtest::query()->where('code', 'numerical')->first();
    $short = $numerical->topics()->first();
    Question::query()->where('topic_id', $short->id)->limit(18)->get()->each->delete();

    $result = app(TestAssembler::class)->assembleMock($this->user, app(Settings::class)->itemSplit(), 3);

    expect($result->questions()->where('subtest_id', $numerical->id)->count())->toBe(40)
        ->and($result->questions()->where('topic_id', $short->id)->count())->toBe(2)
        ->and($result->shortfall())->toBe(0);
});

it('gives the same mock for the same seed', function () {
    fullBank(20);
    $split = app(Settings::class)->itemSplit();

    $first = app(TestAssembler::class)->assembleMock($this->user, $split, 99)->questions()->pluck('id')->all();
    $second = app(TestAssembler::class)->assembleMock($this->user, $split, 99)->questions()->pluck('id')->all();

    expect($first)->toBe($second);
});

it('uses a changed split for new mocks only', function () {
    fullBank(20);
    $start = app(StartAttempt::class);

    $old = $start->mock($this->user, AttemptTiming::Untimed)->attempt;
    $old->update(['status' => AttemptStatus::Abandoned]);
    app(Settings::class)->set(SettingKey::ItemSplitVerbal, 30);
    $new = $start->mock($this->user, AttemptTiming::Untimed)->attempt;

    expect($old->fresh()->item_count)->toBe(170)
        ->and($old->fresh()->config['item_split']['verbal'])->toBe(50)
        ->and($new->mode)->toBe(AttemptMode::Combined)
        ->and($new->item_count)->toBe(150)
        ->and($new->config['item_split']['verbal'])->toBe(30)
        ->and($new->answers()->whereHas('subtest', fn ($q) => $q->where('code', 'verbal'))->count())->toBe(30);
});
