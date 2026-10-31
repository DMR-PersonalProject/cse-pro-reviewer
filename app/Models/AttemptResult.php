<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Database\Factories\AttemptResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The score snapshot written once at finalization (FR-S4). Never recalculated from live data.
 */
#[Fillable([
    'attempt_id', 'formula_version', 'general_rating', 'passing_grade', 'passed', 'correct_count',
    'total_count', 'time_used_seconds', 'end_reason', 'topic_breakdown',
])]
class AttemptResult extends Model
{
    /** @use HasFactory<AttemptResultFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'general_rating' => 'decimal:2',
            'passing_grade' => 'decimal:2',
            'passed' => 'boolean',
            'correct_count' => 'integer',
            'total_count' => 'integer',
            'time_used_seconds' => 'integer',
            'end_reason' => AttemptStatus::class,
            'topic_breakdown' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
