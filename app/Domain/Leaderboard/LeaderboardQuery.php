<?php

declare(strict_types=1);

namespace App\Domain\Leaderboard;

use App\Enums\AttemptMode;
use App\Models\Attempt;
use App\Models\Subtest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The boards (FR-L1, FR-L3): each user's best eligible attempt, ranked by score, then time used,
 * then submit time. Rows hold only rank, ign, score and time_used_seconds. The only raw SQL in
 * the app lives here: the standard ROW_NUMBER() window.
 */
final class LeaderboardQuery
{
    private const ORDER = 'score DESC, time_used_seconds ASC, finalized_at ASC, attempt_id ASC';

    public function __construct(private readonly Eligibility $eligibility) {}

    public function mock(Period $period, int $perPage = 25): LengthAwarePaginator
    {
        $attempts = $this->eligible($period)
            ->where('attempts.mode', AttemptMode::Combined)
            ->join('attempt_results', 'attempt_results.attempt_id', '=', 'attempts.id')
            ->addSelect('attempt_results.general_rating as score');

        return $this->rank($attempts, $perPage);
    }

    public function subtest(Subtest $subtest, Period $period, int $perPage = 25): LengthAwarePaginator
    {
        $attempts = $this->eligible($period)
            ->where('attempts.mode', AttemptMode::Individual)
            ->where('attempts.subtest_id', $subtest->id)
            ->join('attempt_subtest_scores', function ($join) use ($subtest) {
                $join->on('attempt_subtest_scores.attempt_id', '=', 'attempts.id')
                    ->where('attempt_subtest_scores.subtest_id', $subtest->id);
            })
            ->addSelect('attempt_subtest_scores.percent as score');

        return $this->rank($attempts, $perPage);
    }

    /**
     * @return Builder<Attempt>
     */
    private function eligible(Period $period): Builder
    {
        return $this->eligibility->apply(Attempt::query())
            ->select('attempts.id as attempt_id', 'attempts.user_id', 'attempts.time_used_seconds', 'attempts.finalized_at')
            ->when($period->range(), fn (Builder $query, array $range) => $query
                ->where('attempts.finalized_at', '>=', $range[0])
                ->where('attempts.finalized_at', '<', $range[1]));
    }

    /**
     * @param  Builder<Attempt>  $attempts
     */
    private function rank(Builder $attempts, int $perPage): LengthAwarePaginator
    {
        $scored = DB::query()->fromSub($attempts, 'scored')
            ->select('scored.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY '.self::ORDER.') as user_position');

        $best = DB::query()->fromSub($scored, 'best')
            ->where('user_position', 1)
            ->select('best.*')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY '.self::ORDER.') as board_rank');

        return DB::query()->fromSub($best, 'ranked')
            ->join('users', 'users.id', '=', 'ranked.user_id')
            ->select('ranked.board_rank as rank', 'users.ign', 'ranked.score', 'ranked.time_used_seconds')
            ->orderBy('ranked.board_rank')
            ->paginate($perPage);
    }
}
