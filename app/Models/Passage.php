<?php

namespace App\Models;

use App\Enums\Language;
use App\Enums\QuestionStatus;
use Database\Factories\PassageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A shared stem (reading text, table or chart) with several items under it (FR-Q3).
 */
#[Fillable(['subtest_id', 'topic_id', 'source_id', 'title', 'body', 'language', 'status'])]
class Passage extends Model
{
    /** @use HasFactory<PassageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'language' => 'en',
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language' => Language::class,
            'status' => QuestionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Subtest, $this>
     */
    public function subtest(): BelongsTo
    {
        return $this->belongsTo(Subtest::class);
    }

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('passage_position');
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('position');
    }
}
