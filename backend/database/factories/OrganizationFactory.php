<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $externalId = fake()->unique()->numerify('##########');

        return [
            'source' => 'yandex',
            'external_id' => $externalId,
            'source_url' => "https://yandex.ru/maps/org/{$externalId}/",
            'normalized_url' => "https://yandex.ru/maps/org/{$externalId}",
            'name' => fake()->company(),
            'rating' => fake()->randomFloat(2, 1, 5),
            'ratings_count' => fake()->numberBetween(1, 5000),
            'reviews_count' => fake()->numberBetween(1, 600),
            'last_synced_at' => now(),
        ];
    }
}
