<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Enums\QuestionStatus;
use App\Enums\SourceVisibility;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The items a user may be given (FR-G3), split into fresh and recently seen ones (FR-G6).
 */
final class ItemPool
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  list<int>  $subtestIds
     * @param  list<int>  $topicIds  empty means every topic of the subtests
     */
    public function build(User $user, array $subtestIds, array $topicIds = []): PoolItems
    {
        $questions = Question::query()
            ->where('status', QuestionStatus::Published)
            ->whereIn('subtest_id', $subtestIds)
            ->when($topicIds !== [], fn (Builder $query) => $query->whereIn('topic_id', $topicIds))
            ->when(! $user->isAdmin(), fn (Builder $query) => $this->publicOnly($query))
            ->orderBy('id')
            ->get();

        $lastSeen = $this->lastSeen($user);
        [$seen, $fresh] = $questions->partition(fn (Question $question) => isset($lastSeen[$question->id]));

        return new PoolItems(
            fresh: $fresh->values(),
            seen: $seen->sortBy([
                fn (Question $a, Question $b) => $lastSeen[$a->id] <=> $lastSeen[$b->id],
                fn (Question $a, Question $b) => $a->id <=> $b->id,
            ])->values(),
            lastSeen: $lastSeen,
        );
    }

    /**
     * A passage item also needs a public passage source, or the passage text would leak.
     *
     * @param  Builder<Question>  $query
     */
    private function publicOnly(Builder $query): void
    {
        $public = fn (Builder $source) => $source->where('visibility', SourceVisibility::Public);

        $query->whereHas('source', $public)
            ->where(fn (Builder $query) => $query
                ->whereNull('passage_id')
                ->orWhereHas('passage.source', $public));
    }

    /**
     * When each question was last answered in the user's last N attempts, as a Unix timestamp.
     *
     * @return array<int, int>
     */
    private function lastSeen(User $user): array
    {
        $window = (int) $this->settings->get(SettingKey::RepeatWindowAttempts);

        if ($window === 0) {
            return [];
        }

        $attemptIds = $user->attempts()->latest('started_at')->latest('id')->limit($window)->pluck('id');

        $lastSeen = [];

        AttemptAnswer::query()
            ->whereIn('attempt_id', $attemptIds)
            ->whereNotNull('question_id')
            ->whereNotNull('answered_at')
            ->get(['question_id', 'answered_at'])
            ->each(function (AttemptAnswer $answer) use (&$lastSeen) {
                $at = $answer->answered_at->getTimestamp();
                $lastSeen[$answer->question_id] = max($lastSeen[$answer->question_id] ?? 0, $at);
            });

        return $lastSeen;
    }
}
