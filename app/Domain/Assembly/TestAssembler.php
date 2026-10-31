<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\Question;
use App\Models\Topic;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Picks and orders the items of one subtest (plan §6). The seed is stored on the attempt,
 * so the same request and seed always give the same test.
 */
final class TestAssembler
{
    public function __construct(private readonly ItemPool $pool) {}

    public function assemble(AssemblyRequest $request): AssemblyResult
    {
        $randomizer = new Randomizer(new Mt19937($request->seed));

        $pool = $this->pool->build($request->user, [$request->subtestId], $request->topicIds);
        $candidates = $this->drawOrder($this->units($pool), $randomizer);

        $topicIds = Topic::query()
            ->where('subtest_id', $request->subtestId)
            ->when($request->topicIds !== [], fn ($query) => $query->whereIn('id', $request->topicIds))
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $chosen = [];

        foreach ($this->quotas($request->count, $topicIds) as $topicId => $quota) {
            $this->fill($candidates, $chosen, $quota, fn (SelectionUnit $unit) => $unit->topicId === $topicId);
        }

        // A topic that ran short is made up from the other topics (A-3).
        $this->fill($candidates, $chosen, $request->count - $this->size($chosen), fn () => true);

        return new AssemblyResult($randomizer->shuffleArray($chosen), $request->count);
    }

    /**
     * Groups passage items into one unit each, in authored order.
     *
     * @return list<SelectionUnit>
     */
    private function units(PoolItems $pool): array
    {
        return $pool->all()
            ->groupBy(fn (Question $question) => $question->passage_id === null ? 'q'.$question->id : 'p'.$question->passage_id)
            ->map(function ($questions) use ($pool) {
                $seen = $questions->map(fn (Question $question) => $pool->lastSeen[$question->id] ?? null)->filter();

                return new SelectionUnit(
                    topicId: $questions->first()->topic_id,
                    questions: $questions->sortBy('passage_position')->values(),
                    lastSeen: $seen->isEmpty() ? null : $seen->max(),
                );
            })
            ->values()
            ->all();
    }

    /**
     * Fresh units in random order first, then seen units, least recently seen first (FR-G6).
     *
     * @param  list<SelectionUnit>  $units
     * @return list<SelectionUnit>
     */
    private function drawOrder(array $units, Randomizer $randomizer): array
    {
        $fresh = array_values(array_filter($units, fn (SelectionUnit $unit) => $unit->lastSeen === null));
        $seen = array_values(array_filter($units, fn (SelectionUnit $unit) => $unit->lastSeen !== null));
        usort($seen, fn (SelectionUnit $a, SelectionUnit $b) => $a->lastSeen <=> $b->lastSeen);

        return [...$randomizer->shuffleArray($fresh), ...$seen];
    }

    /**
     * An even split; the remainder goes to the first topics by position.
     *
     * @param  list<int>  $topicIds
     * @return array<int, int>
     */
    private function quotas(int $count, array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        $base = intdiv($count, count($topicIds));
        $remainder = $count % count($topicIds);
        $quotas = [];

        foreach ($topicIds as $index => $topicId) {
            $quotas[$topicId] = $base + ($index < $remainder ? 1 : 0);
        }

        return $quotas;
    }

    /**
     * Moves units that fit the remaining quota from the candidates to the chosen list.
     * A unit too big for what is left is skipped, so smaller ones can still fill the gap.
     *
     * @param  list<SelectionUnit>  $candidates
     * @param  list<SelectionUnit>  $chosen
     * @param  callable(SelectionUnit): bool  $eligible
     */
    private function fill(array &$candidates, array &$chosen, int $quota, callable $eligible): void
    {
        foreach ($candidates as $index => $unit) {
            if ($quota <= 0) {
                break;
            }

            if ($unit->size() <= $quota && $eligible($unit)) {
                $chosen[] = $unit;
                $quota -= $unit->size();
                unset($candidates[$index]);
            }
        }
    }

    /**
     * @param  list<SelectionUnit>  $units
     */
    private function size(array $units): int
    {
        return array_sum(array_map(fn (SelectionUnit $unit) => $unit->size(), $units));
    }
}
