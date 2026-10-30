<?php

namespace Database\Factories;

use App\Models\Subtest;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only topics. Real ones come from TaxonomySeeder.
 *
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subtest_id' => Subtest::factory(),
            'code' => 'topic_'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'position' => fake()->numberBetween(1, 9),
        ];
    }
}
