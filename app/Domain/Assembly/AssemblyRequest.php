<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Models\User;

final readonly class AssemblyRequest
{
    /**
     * @param  list<int>  $topicIds  empty means every topic of the subtest
     */
    public function __construct(
        public User $user,
        public int $subtestId,
        public array $topicIds,
        public int $count,
        public int $seed,
    ) {}
}
