<?php

namespace App\Models;

use Database\Factories\SubtestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed taxonomy, seeded by TaxonomySeeder. Codes are used in settings and import files.
 */
#[Fillable(['code', 'name', 'position'])]
class Subtest extends Model
{
    /** @use HasFactory<SubtestFactory> */
    use HasFactory;

    /**
     * @return HasMany<Topic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('position');
    }
}
