<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Content\MediaValidator;
use App\Enums\Difficulty;
use App\Enums\Language;
use App\Models\Subtest;
use Illuminate\Validation\ValidationException;

/**
 * Row errors (plan §8.3): anything that stops a row from being approved. Doubts that a human can
 * resolve later (missing key, missing image, alt text) are flags, not errors; see RowFlagger.
 */
class RowValidator
{
    private const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /** @var array<string, list<string>> topic codes by subtest code */
    private array $taxonomy = [];

    public function __construct(
        private readonly MediaValidator $mediaValidator,
        private readonly StagedMedia $stagedMedia,
    ) {}

    /**
     * @param  ?string  $mediaRoot  the staged folder; null when files can't come along (admin upload)
     * @return list<list<string>> errors for each row, in row order
     */
    public function validate(ParsedImport $import, ?string $mediaRoot): array
    {
        $this->taxonomy = Subtest::with('topics')->get()
            ->mapWithKeys(fn (Subtest $subtest) => [$subtest->code => $subtest->topics->pluck('code')->all()])
            ->all();

        $refCounts = array_count_values(array_column($import->passages, 'ref'));
        $passages = [];

        foreach ($import->passages as $passage) {
            $passages[$passage['ref']] = $passage;
        }

        $passageErrors = array_map(fn (array $passage) => $this->passageErrors($passage, $mediaRoot), $passages);

        return array_map(function (array $row) use ($passages, $passageErrors, $refCounts, $mediaRoot) {
            $errors = [...$this->rowErrors($row), ...$this->mediaErrors($row['media'], $mediaRoot)];
            $ref = $row['passage_ref'];

            if ($ref !== null) {
                if (! isset($passages[$ref])) {
                    $errors[] = __('Unknown passage_ref “:ref”.', ['ref' => $ref]);
                } else {
                    if ($refCounts[$ref] > 1) {
                        $errors[] = __('Passage ref “:ref” is used by more than one passage.', ['ref' => $ref]);
                    }

                    if ($row['subtest'] !== $passages[$ref]['subtest'] || $row['topic'] !== $passages[$ref]['topic']) {
                        $errors[] = __('The item’s subtest and topic must match passage :ref.', ['ref' => $ref]);
                    }

                    foreach ($passageErrors[$ref] as $error) {
                        $errors[] = __('Passage :ref: :error', ['ref' => $ref, 'error' => $error]);
                    }
                }
            }

            return $errors;
        }, $import->rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function rowErrors(array $row): array
    {
        $errors = $this->taxonomyErrors($row['subtest'], $row['topic']);

        if (Language::tryFrom($row['language']) === null) {
            $errors[] = __('Unknown language “:code”.', ['code' => $row['language']]);
        }

        if ($row['difficulty'] !== null && Difficulty::tryFrom($row['difficulty']) === null) {
            $errors[] = __('Unknown difficulty “:code”.', ['code' => $row['difficulty']]);
        }

        if ($row['stem'] === '') {
            $errors[] = __('The stem is empty.');
        }

        $count = count($row['choices']);

        if ($count < 2 || $count > 5) {
            $errors[] = __('An item needs 2–5 choices; this row has :count.', ['count' => $count]);
        }

        foreach (array_slice($row['choices'], 0, 5) as $index => $choice) {
            if ($choice === '') {
                $errors[] = __('Choice :letter is empty.', ['letter' => self::LETTERS[$index]]);
            }
        }

        if ($row['answer'] !== null && ! in_array($row['answer'], array_slice(self::LETTERS, 0, $count), true)) {
            $errors[] = __('The answer “:answer” doesn’t match a choice.', ['answer' => $row['answer']]);
        }

        $confidence = $row['extraction']['confidence'];

        if ($confidence !== null && ($confidence < 0 || $confidence > 1)) {
            $errors[] = __('Confidence must be between 0 and 1.');
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $passage
     * @return list<string>
     */
    private function passageErrors(array $passage, ?string $mediaRoot): array
    {
        $errors = $this->taxonomyErrors($passage['subtest'], $passage['topic']);

        if (Language::tryFrom($passage['language']) === null) {
            $errors[] = __('Unknown language “:code”.', ['code' => $passage['language']]);
        }

        if ($passage['body'] === null && $passage['media'] === []) {
            $errors[] = __('The passage has no text and no image.');
        }

        return [...$errors, ...$this->mediaErrors($passage['media'], $mediaRoot)];
    }

    /**
     * @return list<string>
     */
    private function taxonomyErrors(string $subtest, string $topic): array
    {
        if (! isset($this->taxonomy[$subtest])) {
            return [__('Unknown subtest “:code”.', ['code' => $subtest])];
        }

        if (! in_array($topic, $this->taxonomy[$subtest], true)) {
            return [__('Unknown topic “:code” for subtest :subtest.', ['code' => $topic, 'subtest' => $subtest])];
        }

        return [];
    }

    /**
     * Type and size of each staged image that exists. A missing file or missing alt text is a flag.
     *
     * @param  list<array{path: string, alt: ?string}>  $media
     * @return list<string>
     */
    public function mediaErrors(array $media, ?string $mediaRoot): array
    {
        $errors = [];

        foreach ($media as $item) {
            if (! $this->stagedMedia->isSafe($item['path'])) {
                $errors[] = __('Image :path must be a relative path inside content/staged/.', ['path' => $item['path']]);

                continue;
            }

            $file = $this->stagedMedia->resolve($mediaRoot, $item['path']);

            if ($file === null) {
                continue;
            }

            try {
                $this->mediaValidator->validate($file, $item['alt']);
            } catch (ValidationException $e) {
                // Only type and size are errors; missing alt text is the flagger's.
                foreach ($e->errors()['file'] ?? [] as $error) {
                    $errors[] = __('Image :path: :error', ['path' => $item['path'], 'error' => $error]);
                }
            }
        }

        return $errors;
    }
}
