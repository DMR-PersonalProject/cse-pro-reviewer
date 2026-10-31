<?php

namespace App\Models;

use App\Enums\ImportRowDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of a batch: its normalized payload, errors (block approval) and flags (lead to
 * needs_review). Errors are a list of messages; flags a list of {code, detail}.
 */
#[Fillable([
    'import_batch_id', 'row_number', 'payload', 'errors', 'flags', 'duplicate_of_question_id',
    'decision', 'question_id',
])]
class ImportRow extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'decision' => 'pending',
        'errors' => '[]',
        'flags' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'payload' => 'array',
            'errors' => 'array',
            'flags' => 'array',
            'decision' => ImportRowDecision::class,
        ];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'duplicate_of_question_id');
    }
}
