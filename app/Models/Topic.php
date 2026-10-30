<?php

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
