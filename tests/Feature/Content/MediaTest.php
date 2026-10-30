<?php

use App\Domain\Content\AttachMedia;
use App\Domain\Content\MediaValidator;
use App\Models\Media;
use App\Models\Passage;
use App\Models\Question;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Writes a real image (or other content) to a temp file with the given name.
 */
function mediaFile(string $name, string $kind, int $padToBytes = 0): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cse');
    $image = imagecreatetruecolor(40, 30);

    match ($kind) {
        'png' => imagepng($image, $path),
        'jpg' => imagejpeg($image, $path),
        'webp' => imagewebp($image, $path),
        'gif' => imagegif($image, $path),
        'svg' => file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>'),
        'html' => file_put_contents($path, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'),
    };

    if ($padToBytes > 0) {
        // Bytes after the image data keep it a valid image of the detected type.
        file_put_contents($path, str_repeat("\0", $padToBytes - filesize($path)), FILE_APPEND);
    }

    return new UploadedFile($path, $name, null, null, true);
}

function validationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

beforeEach(function () {
    Storage::fake('public');
    $this->validator = app(MediaValidator::class);
});

it('accepts PNG, JPG and WebP, reading the real type and size', function (string $kind, string $mime) {
    $result = $this->validator->validate(mediaFile("photo.{$kind}", $kind), 'A gray box');

    expect($result)
        ->mime->toBe($mime)
        ->width->toBe(40)
        ->height->toBe(30)
        ->size->toBeGreaterThan(0);
})->with([
    'png' => ['png', 'image/png'],
    'jpg' => ['jpg', 'image/jpeg'],
    'webp' => ['webp', 'image/webp'],
]);

it('rejects unsupported image types, whatever the file is called', function (string $name, string $kind) {
    $errors = validationErrors(fn () => $this->validator->validate(mediaFile($name, $kind), 'Alt'));

    expect($errors['file'] ?? [])->toContain(__('Unsupported image type. Use PNG, JPG or WebP.'));
})->with([
    'svg' => ['figure.svg', 'svg'],
    'svg named .png' => ['figure.png', 'svg'],
    'html named .png' => ['figure.png', 'html'],
    'gif' => ['figure.gif', 'gif'],
]);

it('rejects files over 2 MB', function () {
    $file = mediaFile('big.png', 'png', padToBytes: (int) (2.1 * 1024 * 1024));

    $errors = validationErrors(fn () => $this->validator->validate($file, 'Alt'));

    expect($errors['file'] ?? [])->toContain(__('File too large. The limit is 2 MB.'));
});

it('accepts a file of exactly 2 MB', function () {
    $file = mediaFile('edge.png', 'png', padToBytes: 2 * 1024 * 1024);

    expect($this->validator->validate($file, 'Alt')->size)->toBe(2 * 1024 * 1024);
});

it('requires alt text (AC-21a, editor side)', function (?string $alt) {
    $errors = validationErrors(fn () => $this->validator->validate(mediaFile('photo.png', 'png'), $alt));

    expect($errors)->toHaveKey('alt_text');
})->with(['null' => null, 'empty' => '', 'spaces' => '   ']);

it('stores an attached image under media/ on the public disk with a random name', function () {
    $question = Question::factory()->create();

    $media = app(AttachMedia::class)->handle($question, mediaFile('My Chart (final).PNG', 'png'), '  Bar chart of sales  ');

    expect($media)
        ->disk->toBe('public')
        ->mime->toBe('image/png')
        ->alt_text->toBe('Bar chart of sales')
        ->position->toBe(1)
        ->and($media->path)->toStartWith('media/')->toEndWith('.png')->not->toContain('Chart')
        ->and($media->mediable->is($question))->toBeTrue();
    Storage::disk('public')->assertExists($media->path);
});

it('names the file by its real type, not its extension', function () {
    $media = app(AttachMedia::class)->handle(Question::factory()->create(), mediaFile('photo.png', 'jpg'), 'Alt');

    expect($media->path)->toEndWith('.jpg')->and($media->mime)->toBe('image/jpeg');
});

it('appends media after existing ones', function () {
    $passage = Passage::factory()->create();
    app(AttachMedia::class)->handle($passage, mediaFile('a.png', 'png'), 'First');

    $second = app(AttachMedia::class)->handle($passage, mediaFile('b.webp', 'webp'), 'Second');

    expect($second->position)->toBe(2);
});

it('stores nothing when validation fails', function () {
    $question = Question::factory()->create();

    validationErrors(fn () => app(AttachMedia::class)->handle($question, mediaFile('x.svg', 'svg'), 'Alt'));

    expect(Media::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
