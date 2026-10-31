<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded or staged file (FR-M2). `meta` keeps the file's default source, passages by ref
 * and warnings until its rows are approved.
 */
#[Fillable([
    'user_id', 'name', 'original_filename', 'format', 'default_source_id', 'status', 'meta',
    'total_rows', 'valid_rows', 'approved_rows', 'rejected_rows',
])]
class ImportBatch extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'total_rows' => 0,
        'valid_rows' => 0,
        'approved_rows' => 0,
        'rejected_rows' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ImportFormat::class,
            'status' => ImportBatchStatus::class,
            'meta' => 'array',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'approved_rows' => 'integer',
            'rejected_rows' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function defaultSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'default_source_id');
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
