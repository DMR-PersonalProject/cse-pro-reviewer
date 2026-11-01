<?php

namespace App\Models;

use Database\Factories\AttemptSubtestScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subtest's share of a finalized attempt; subtest boards rank on `percent`.
 */
#[Fillable(['attempt_id', 'subtest_id', 'correct', 'total', 'percent'])]
class AttemptSubtestScore extends Model
{
    /** @use HasFactory<AttemptSubtestScoreFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correct' => 'integer',
            'total' => 'integer',
            'percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * @return BelongsTo<Subtest, $this>
     */
    public function subtest(): BelongsTo
    {
        return $this->belongsTo(Subtest::class);
    }
}
