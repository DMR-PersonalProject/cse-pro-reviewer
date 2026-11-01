<?php

namespace App\Console\Commands;

use App\Domain\Attempts\FinalizeExpiredAttempts;
use Illuminate\Console\Command;

/**
 * Optional global sweep (plan §5 rule 6). Pages already finalize a user's own expired attempts.
 */
class CseFinalizeExpired extends Command
{
    protected $signature = 'cse:finalize-expired';

    protected $description = 'Finalize timed attempts whose time and grace have run out';

    public function handle(FinalizeExpiredAttempts $expired): int
    {
        $this->info(__('Finalized :count attempt(s).', ['count' => $expired->all()]));

        return self::SUCCESS;
    }
}
