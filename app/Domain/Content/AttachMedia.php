<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Models\Choice;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Question;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates an image and stores it on the public disk under media/ with a random name.
 */
class AttachMedia
{
    public const DISK = 'public';

    public const DIRECTORY = 'media';

    public function __construct(private readonly MediaValidator $validator) {}

    /**
     * @throws ValidationException
     */
    public function handle(Question|Passage|Choice $mediable, UploadedFile|string $file, ?string $altText): Media
    {
        $image = $this->validator->validate($file, $altText);

        $path = Storage::disk(self::DISK)->putFileAs(
            self::DIRECTORY,
            new File($image->path),
            Str::random(40).'.'.$image->extension,
        );

        return $mediable->media()->create([
            'disk' => self::DISK,
            'path' => $path,
            'mime' => $image->mime,
            'size' => $image->size,
            'width' => $image->width,
            'height' => $image->height,
            'alt_text' => $image->altText,
            'position' => ($mediable->media()->max('position') ?? 0) + 1,
        ]);
    }
}
