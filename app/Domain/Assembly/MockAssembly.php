<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * A combined mock: one assembly per subtest, in the fixed subtest order (plan §6.6).
 */
final readonly class MockAssembly
{
    /**
     * @param  array<string, AssemblyResult>  $parts  subtest code => its items, in shown order
     */
    public function __construct(public array $parts) {}

    /**
     * @return Collection<int, Question>
     */
    public function questions(): Collection
    {
        return collect($this->parts)->flatMap(fn (AssemblyResult $part) => $part->questions())->values();
    }

    public function count(): int
    {
        return array_sum(array_map(fn (AssemblyResult $part) => $part->count(), $this->parts));
    }

    public function requested(): int
    {
        return array_sum(array_map(fn (AssemblyResult $part) => $part->requested, $this->parts));
    }

    public function shortfall(): int
    {
        return max(0, $this->requested() - $this->count());
    }
}
