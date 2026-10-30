<?php

namespace Database\Seeders;

use App\Models\Subtest;
use Illuminate\Database\Seeder;

/**
 * The 4 subtests and 17 topics (codes chosen 2026-10-30, plan §3.2). Safe to re-run: rows are
 * matched by code, so ids stay stable and names and positions are restored.
 */
class TaxonomySeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, topics: array<string, string>}>
     */
    public const TAXONOMY = [
        'verbal' => ['name' => 'Verbal Ability', 'topics' => [
            'word_meaning' => 'Word meaning (vocabulary)',
            'sentence_completion' => 'Sentence completion',
            'error_recognition' => 'Error recognition',
            'sentence_structure' => 'Sentence structure',
            'paragraph_organization' => 'Paragraph organization',
            'reading_comprehension' => 'Reading comprehension',
        ]],
        'numerical' => ['name' => 'Numerical Ability', 'topics' => [
            'basic_operations' => 'Basic operations',
            'number_sequences' => 'Number sequences',
            'word_problems' => 'Word problems',
        ]],
        'analytical' => ['name' => 'Analytical Ability', 'topics' => [
            'word_analogy' => 'Word analogy',
            'abstract_reasoning' => 'Symbolic logic and abstract reasoning',
            'assumptions_conclusions' => 'Assumptions and conclusions',
            'data_interpretation' => 'Data interpretation',
        ]],
        'general_info' => ['name' => 'General Information', 'topics' => [
            'philippine_constitution' => 'Philippine Constitution',
            'ra_6713' => 'R.A. 6713 (Code of Conduct and Ethical Standards)',
            'peace_human_rights' => 'Peace and human rights',
            'environment' => 'Environmental management and protection',
        ]],
    ];

    public function run(): void
    {
        $subtestPosition = 0;

        foreach (self::TAXONOMY as $code => $subtest) {
            $model = Subtest::updateOrCreate(
                ['code' => $code],
                ['name' => $subtest['name'], 'position' => ++$subtestPosition],
            );

            $topicPosition = 0;

            foreach ($subtest['topics'] as $topicCode => $name) {
                $model->topics()->updateOrCreate(
                    ['code' => $topicCode],
                    ['name' => $name, 'position' => ++$topicPosition],
                );
            }
        }
    }
}
