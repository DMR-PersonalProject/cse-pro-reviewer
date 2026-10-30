<?php

namespace App\Models;

use App\Domain\Content\StemNormalizer;
use App\Enums\Difficulty;
use App\Enums\Language;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One multiple-choice item (FR-Q1). Status changes go through PublishQuestion/ArchiveQuestion.
 */
#[Fillable([
    'subtest_id', 'topic_id', 'passage_id', 'passage_position', 'source_id',
    'stem', 'explanation', 'difficulty', 'language', 'status', 'needs_review_reasons',
    'lock_choice_order', 'extraction_confidence', 'source_file', 'source_page', 'source_notes',
    'published_at', 'archived_at',
])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'language' => 'en',
        'status' => 'draft',
        'needs_review_reasons' => '[]',
        'lock_choice_order' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'language' => Language::class,
            'status' => QuestionStatus::class,
            'needs_review_reasons' => AsEnumCollection::of(NeedsReviewReason::class),
            'lock_choice_order' => 'boolean',
            'extraction_confidence' => 'decimal:2',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Question $question) {
            $question->stem_normalized = app(StemNormalizer::class)->normalize($question->stem);
        });
    }

    public function correctChoice(): ?Choice
    {
        return $this->choices->firstWhere('is_correct', true);
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
     * @return BelongsTo<Passage, $this>
     */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return HasMany<Choice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(Choice::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('position');
    }
}
