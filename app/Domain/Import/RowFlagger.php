<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Content\DuplicateDetector;
use App\Domain\Content\StemNormalizer;
use App\Enums\ImportFlag;
use App\Models\Subtest;

/**
 * Row flags (plan §8.3, FR-M4): doubts a human resolves after import. A flagged row can be approved,
 * and its item arrives as needs_review. Nothing here fills in a missing answer or alt text.
 */
class RowFlagger
{
    public const LOW_CONFIDENCE = 0.8;

    public function __construct(
        private readonly DuplicateDetector $duplicates,
        private readonly StemNormalizer $normalizer,
        private readonly StagedMedia $stagedMedia,
    ) {}

    /**
     * @param  ?string  $mediaRoot  the staged folder; null when files can't come along (admin upload)
     * @return list<array{flags: list<array{code: string, detail: ?string}>, duplicate_of_question_id: ?int}>
     */
    public function flag(ParsedImport $import, ?string $mediaRoot): array
    {
        $subtestIds = Subtest::pluck('id', 'code')->all();
        $passages = [];

        foreach ($import->passages as $passage) {
            $passages[$passage['ref']] ??= $passage;
        }

        $threshold = $this->duplicates->threshold();
        $seen = [];
        $results = [];

        foreach ($import->rows as $row) {
            $flags = [];
            $duplicateOf = null;

            if ($row['answer'] === null) {
                $flags[] = $this->flagOf(ImportFlag::MissingAnswerKey);
            }

            if ($row['explanation'] === null) {
                $flags[] = $this->flagOf(ImportFlag::MissingExplanation);
            }

            $confidence = $row['extraction']['confidence'];

            if ($confidence !== null && $confidence < self::LOW_CONFIDENCE) {
                $flags[] = $this->flagOf(ImportFlag::LowConfidence, __('Confidence :value.', ['value' => $confidence]));
            }

            $media = [...($passages[$row['passage_ref'] ?? '']['media'] ?? []), ...$row['media']];
            $flags = [...$flags, ...$this->mediaFlags($media, $row['provenance'], $mediaRoot)];

            if (isset($subtestIds[$row['subtest']]) && $row['stem'] !== '') {
                $match = $this->duplicates->find($row['stem'], $subtestIds[$row['subtest']]);
                $normalized = $this->normalizer->normalize($row['stem']);

                if ($match) {
                    $duplicateOf = $match->question->id;
                    $flags[] = $this->flagOf(ImportFlag::PossibleDuplicate, __('Similar to item #:id (:percent%).', [
                        'id' => $match->question->id,
                        'percent' => round($match->similarity * 100),
                    ]));
                } elseif ($earlier = $this->earlierMatch($seen[$row['subtest']] ?? [], $normalized, $threshold)) {
                    $flags[] = $this->flagOf(ImportFlag::PossibleDuplicate, __('Similar to :row (:percent%).', [
                        'row' => __('row :row in this file', ['row' => $earlier['row']]),
                        'percent' => round($earlier['similarity'] * 100),
                    ]));
                }

                $seen[$row['subtest']][] = ['row' => $row['row_number'], 'stem' => $normalized];
            }

            if ($row['extraction']['needs_review']) {
                $flags[] = $this->flagOf(ImportFlag::ExtractorFlagged, $row['extraction']['notes']);
            }

            $results[] = ['flags' => $flags, 'duplicate_of_question_id' => $duplicateOf];
        }

        return $results;
    }

    /**
     * @param  list<array{path: string, alt: ?string}>  $media
     * @param  array{file: ?string, page: ?string, notes: ?string}  $provenance
     * @return list<array{code: string, detail: ?string}>
     */
    private function mediaFlags(array $media, array $provenance, ?string $mediaRoot): array
    {
        $flags = [];
        $from = $this->from($provenance);

        foreach ($media as $item) {
            if (! $this->stagedMedia->isSafe($item['path'])) {
                continue; // a row error already
            }

            if ($mediaRoot === null) {
                $flags[] = $this->flagOf(ImportFlag::MediaMissing, __('Image :path:from was not uploaded; attach it in the editor.', [
                    'path' => $item['path'], 'from' => $from,
                ]));
            } elseif ($this->stagedMedia->resolve($mediaRoot, $item['path']) === null) {
                $flags[] = $this->flagOf(ImportFlag::MediaMissing, __('Image :path:from is not in content/staged/; attach it in the editor.', [
                    'path' => $item['path'], 'from' => $from,
                ]));
            } elseif ($item['alt'] === null) {
                $flags[] = $this->flagOf(ImportFlag::MissingAltText, __('Image :path:from was found but has no alt text, so it was not attached. Add alt text and attach it in the editor.', [
                    'path' => $item['path'], 'from' => $from,
                ]));
            }
        }

        return $flags;
    }

    /**
     * @param  list<array{row: int, stem: string}>  $earlier
     * @return array{row: int, similarity: float}|null
     */
    private function earlierMatch(array $earlier, string $normalized, float $threshold): ?array
    {
        $best = null;

        foreach ($earlier as $candidate) {
            $similarity = $this->duplicates->similarity($normalized, $candidate['stem'], $threshold);

            if ($similarity !== null && $similarity > ($best['similarity'] ?? 0)) {
                $best = ['row' => $candidate['row'], 'similarity' => $similarity];
            }
        }

        return $best;
    }

    /**
     * " (from reviewer-x.pdf, page 12)", or empty when the row names no file.
     *
     * @param  array{file: ?string, page: ?string, notes: ?string}  $provenance
     */
    private function from(array $provenance): string
    {
        if ($provenance['file'] === null) {
            return '';
        }

        return $provenance['page'] === null
            ? ' '.__('(from :file)', ['file' => $provenance['file']])
            : ' '.__('(from :file, page :page)', ['file' => $provenance['file'], 'page' => $provenance['page']]);
    }

    /**
     * @return array{code: string, detail: ?string}
     */
    private function flagOf(ImportFlag $flag, ?string $detail = null): array
    {
        return ['code' => $flag->value, 'detail' => $detail];
    }
}
