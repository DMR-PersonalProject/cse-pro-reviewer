<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An image on a question, passage or choice. Create it through AttachMedia, which validates it.
 */
#[Fillable(['disk', 'path', 'mime', 'size', 'width', 'height', 'alt_text', 'position'])]
class Media extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    public function hasAltText(): bool
    {
        return trim((string) $this->alt_text) !== '';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
