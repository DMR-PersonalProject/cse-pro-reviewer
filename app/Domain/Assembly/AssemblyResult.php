<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\Question;
use Illuminate\Support\Collection;

final readonly class AssemblyResult
{
    /**
     * @param  list<SelectionUnit>  $units  in the order they are shown
     */
    public function __construct(
        public array $units,
        public int $requested,
    ) {}

    /**
     * @return Collection<int, Question>
     */
    public function questions(): Collection
    {
        return collect($this->units)->flatMap(fn (SelectionUnit $unit) => $unit->questions)->values();
    }

    public function count(): int
    {
        return array_sum(array_map(fn (SelectionUnit $unit) => $unit->size(), $this->units));
    }

    public function shortfall(): int
    {
        return max(0, $this->requested - $this->count());
    }
}
