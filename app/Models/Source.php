<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where items come from (FR-Q4). New sources are private.
 */
#[Fillable(['name', 'type', 'visibility', 'notes', 'created_by'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'visibility' => SourceVisibility::class,
        ];
    }

    public function isPublic(): bool
    {
        return $this->visibility === SourceVisibility::Public;
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return HasMany<Passage, $this>
     */
    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
