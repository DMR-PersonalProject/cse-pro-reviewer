<?php

namespace App\Models;

use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One run of a test by one user. `config` freezes the settings used at the start (FR-C2).
 */
#[Fillable([
    'user_id', 'mode', 'timing', 'subtest_id', 'topic_ids', 'item_count', 'config',
    'is_standard', 'is_partial', 'has_private_items', 'status', 'started_at', 'deadline_at',
    'finalized_at', 'time_used_seconds', 'current_position', 'seed', 'is_suspicious',
    'hidden_at', 'hidden_by', 'hidden_reason',
])]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
        'is_standard' => false,
        'is_partial' => false,
        'has_private_items' => false,
        'is_suspicious' => false,
        'current_position' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => AttemptMode::class,
            'timing' => AttemptTiming::class,
            'status' => AttemptStatus::class,
            'topic_ids' => 'array',
            'item_count' => 'integer',
            'config' => 'array',
            'is_standard' => 'boolean',
            'is_partial' => 'boolean',
            'has_private_items' => 'boolean',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'finalized_at' => 'datetime',
            'time_used_seconds' => 'integer',
            'current_position' => 'integer',
            'seed' => 'integer',
            'is_suspicious' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subtest, $this>
     */
    public function subtest(): BelongsTo
    {
        return $this->belongsTo(Subtest::class);
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class)->orderBy('position');
    }

    /**
     * @return HasOne<AttemptResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(AttemptResult::class);
    }

    /**
     * @return HasMany<AttemptSubtestScore, $this>
     */
    public function subtestScores(): HasMany
    {
        return $this->hasMany(AttemptSubtestScore::class);
    }
}
