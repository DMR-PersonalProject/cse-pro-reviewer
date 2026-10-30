<?php

declare(strict_types=1);

namespace App\Domain\Content;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Checks an image by its real content, not its name (FR-Q1, FR-M4a, AC-21a):
 * PNG, JPG or WebP only, at most 2 MB, with alt text. SVG is never accepted in the MVP.
 */
class MediaValidator
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Allowed MIME types and the extension a stored file gets.
     */
    public const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * @throws ValidationException with "file" and/or "alt_text" errors
     */
    public function validate(UploadedFile|string $file, ?string $altText): ValidatedMedia
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $altText = trim((string) $altText);
        $errors = [];

        $size = is_string($path) && is_file($path) ? (int) filesize($path) : 0;
        $mime = $size > 0 ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($path) : '';
        $dimensions = isset(self::TYPES[$mime]) ? @getimagesize($path) : false;

        if ($dimensions === false) {
            $errors['file'][] = __('Unsupported image type. Use PNG, JPG or WebP.');
        } elseif ($size > self::MAX_BYTES) {
            $errors['file'][] = __('File too large. The limit is 2 MB.');
        }

        if ($altText === '') {
            $errors['alt_text'][] = __('Alt text is required. Describe what the image shows.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new ValidatedMedia(
            path: $path,
            mime: $mime,
            extension: self::TYPES[$mime],
            size: $size,
            width: $dimensions[0],
            height: $dimensions[1],
            altText: $altText,
        );
    }
}
