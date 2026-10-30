<?php

namespace Database\Factories;

use App\Models\Subtest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only subtests. Real ones come from TaxonomySeeder.
 *
 * @extends Factory<Subtest>
 */
class SubtestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'subtest_'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'position' => fake()->numberBetween(1, 9),
        ];
    }
}
