<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * A private source, matching the default for new sources (FR-Q4).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'type' => SourceType::Original,
            'visibility' => SourceVisibility::Private,
            'notes' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['visibility' => SourceVisibility::Public]);
    }
}
