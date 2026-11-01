<?php

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'position'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Subtest, $this>
     */
    public function subtest(): BelongsTo
    {
        return $this->belongsTo(Subtest::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Answer rows keep their topic after the question is deleted (progress accuracy).
     *
     * @return HasMany<AttemptAnswer, $this>
     */
    public function attemptAnswers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
