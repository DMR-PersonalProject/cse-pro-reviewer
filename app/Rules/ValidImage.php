<?php

namespace App\Rules;

use App\Domain\Content\MediaValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Runs MediaValidator's file checks on an upload, so the editor and imports reject the same
 * files with the same messages (AC-21a). Alt text is validated by its own field.
 */
class ValidImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        try {
            app(MediaValidator::class)->validate($value, 'alt text checked separately');
        } catch (ValidationException $e) {
            foreach ($e->errors()['file'] ?? [] as $message) {
                $fail($message);
            }
        }
    }
}
