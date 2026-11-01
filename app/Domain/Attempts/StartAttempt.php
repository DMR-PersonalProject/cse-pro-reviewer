<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Domain\Assembly\AssemblyRequest;
use App\Domain\Assembly\SnapshotBuilder;
use App\Domain\Assembly\TestAssembler;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Enums\SourceVisibility;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Subtest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Creates an attempt with all its snapshotted answer rows in one transaction (plan §6.8).
 * Locking the user row enforces one attempt in progress per user (A-7).
 */
final class StartAttempt
{
    public function __construct(
        private readonly TestAssembler $assembler,
        private readonly SnapshotBuilder $snapshots,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  list<int>  $topicIds  empty means every topic of the subtest
     * @param  bool  $allowShortfall  start with fewer items than requested (the user accepted the offer)
     */
    public function individual(User $user, Subtest $subtest, array $topicIds, int $count, AttemptTiming $timing, bool $allowShortfall = false): StartResult
    {
        return DB::transaction(function () use ($user, $subtest, $topicIds, $count, $timing, $allowShortfall) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $active = Attempt::query()->where('user_id', $user->id)->where('status', AttemptStatus::InProgress)->first();

            if ($active !== null) {
                return new StartResult(active: $active);
            }

            $seed = random_int(1, 2_147_483_647);
            $assembly = $this->assembler->assemble(new AssemblyRequest($user, $subtest->id, $topicIds, $count, $seed));

            if ($assembly->count() === 0 || ($assembly->shortfall() > 0 && ! $allowShortfall)) {
                return new StartResult(available: $assembly->count());
            }

            $config = $this->config();
            $isPartial = $assembly->shortfall() > 0;

            $attempt = Attempt::create([
                'user_id' => $user->id,
                'mode' => AttemptMode::Individual,
                'timing' => $timing,
                'subtest_id' => $subtest->id,
                'topic_ids' => $topicIds === [] ? null : array_values($topicIds),
                'item_count' => $assembly->count(),
                'config' => $config,
                'is_standard' => $timing === AttemptTiming::Timed && ! $isPartial && $topicIds === []
                    && $count === ($config['item_split'][$subtest->code] ?? null),
                'is_partial' => $isPartial,
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
                'deadline_at' => $timing === AttemptTiming::Timed ? now()->addMinutes(self::durationMinutes($assembly->count(), $config['total_minutes'], array_sum($config['item_split']))) : null,
                'seed' => $seed,
            ]);

            $attempt->update(['has_private_items' => $this->writeAnswers($attempt, $assembly->questions(), $seed)]);

            return new StartResult(attempt: $attempt->load('answers'));
        });
    }

    /**
     * A combined mock (FR-G2): the configured split, one clock for the whole exam.
     *
     * @param  bool  $allowShortfall  start a partial mock (the user accepted the offer, FR-G7)
     */
    public function mock(User $user, AttemptTiming $timing, bool $allowShortfall = false): StartResult
    {
        return DB::transaction(function () use ($user, $timing, $allowShortfall) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $active = Attempt::query()->where('user_id', $user->id)->where('status', AttemptStatus::InProgress)->first();

            if ($active !== null) {
                return new StartResult(active: $active);
            }

            $config = $this->config();
            $seed = random_int(1, 2_147_483_647);
            $assembly = $this->assembler->assembleMock($user, $config['item_split'], $seed);

            if ($assembly->count() === 0 || ($assembly->shortfall() > 0 && ! $allowShortfall)) {
                return new StartResult(available: $assembly->count(), subtests: $assembly->subtestCounts());
            }

            $isPartial = $assembly->shortfall() > 0;

            $attempt = Attempt::create([
                'user_id' => $user->id,
                'mode' => AttemptMode::Combined,
                'timing' => $timing,
                'item_count' => $assembly->count(),
                'config' => $config,
                'is_standard' => $timing === AttemptTiming::Timed && ! $isPartial,
                'is_partial' => $isPartial,
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
                'deadline_at' => $timing === AttemptTiming::Timed ? now()->addMinutes(self::durationMinutes($assembly->count(), $config['total_minutes'], $assembly->requested())) : null,
                'seed' => $seed,
            ]);

            $attempt->update(['has_private_items' => $this->writeAnswers($attempt, $assembly->questions(), $seed)]);

            return new StartResult(attempt: $attempt->load('answers'));
        });
    }

    /**
     * Creates one answer row per item, numbered from 1, and says whether any item is private.
     *
     * @param  Collection<int, Question>  $questions
     */
    private function writeAnswers(Attempt $attempt, Collection $questions, int $seed): bool
    {
        $randomizer = new Randomizer(new Mt19937($seed));
        $hasPrivate = false;

        foreach ($questions->values() as $index => $question) {
            $item = $this->snapshots->build($question, $randomizer);
            $hasPrivate = $hasPrivate || $item['snapshot']['source_visibility'] === SourceVisibility::Private->value;

            $attempt->answers()->create([
                'question_id' => $question->id,
                'position' => $index + 1,
                'subtest_id' => $question->subtest_id,
                'topic_id' => $question->topic_id,
                'passage_key' => $question->passage_id === null ? null : 'p'.$question->passage_id,
                'snapshot' => $item['snapshot'],
                'correct_key' => $item['correct_key'],
            ]);
        }

        return $hasPrivate;
    }

    /**
     * The settings this attempt keeps for its whole life (FR-C2).
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'item_split' => $this->settings->itemSplit(),
            'total_minutes' => (int) $this->settings->get(SettingKey::TotalMinutes),
            'passing_grade' => (float) $this->settings->get(SettingKey::PassingGrade),
            'grace_seconds' => (int) $this->settings->get(SettingKey::AutosaveGraceSeconds),
            'suspicious_seconds_per_item' => (int) $this->settings->get(SettingKey::SuspiciousSecondsPerItem),
        ];
    }

    /**
     * ⌈total_minutes × n ÷ total_items⌉ (FR-G1), in integers. Public for the setup page preview.
     */
    public static function durationMinutes(int $items, int $totalMinutes, int $totalItems): int
    {
        return intdiv($totalMinutes * $items + $totalItems - 1, $totalItems);
    }
}
