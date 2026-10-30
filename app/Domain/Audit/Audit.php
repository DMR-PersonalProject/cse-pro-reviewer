<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    /**
     * Record an admin-relevant action, attributed to the signed-in user (null from the console).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, ?string $reason = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }
}
