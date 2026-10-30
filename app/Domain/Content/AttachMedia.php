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
     * A random stored name with the extension of the file's real type (never the uploaded name).
     */
    public static function storageName(string $mime): string
    {
        return Str::random(40).'.'.(MediaValidator::TYPES[$mime] ?? 'bin');
    }

    /**
     * Media columns for a file already stored on the public disk (used by the Filament editor).
     *
     * @return array{disk: string, mime: string, size: int, width: int, height: int, alt_text: string}
     *
     * @throws ValidationException
     */
    public function metadataFor(string $path, ?string $altText): array
    {
        $image = $this->validator->validate(Storage::disk(self::DISK)->path($path), $altText);

        return [
            'disk' => self::DISK,
            'mime' => $image->mime,
            'size' => $image->size,
            'width' => $image->width,
            'height' => $image->height,
            'alt_text' => $image->altText,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function handle(Question|Passage|Choice $mediable, UploadedFile|string $file, ?string $altText): Media
    {
        $image = $this->validator->validate($file, $altText);

        $path = Storage::disk(self::DISK)->putFileAs(
            self::DIRECTORY,
            new File($image->path),
            self::storageName($image->mime),
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
