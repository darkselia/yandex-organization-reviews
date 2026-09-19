<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationSnapshot> */
class OrganizationSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parse_run_id' => null,
            'rating' => fake()->randomFloat(2, 1, 5),
            'ratings_count' => fake()->numberBetween(1, 5000),
            'reviews_count' => fake()->numberBetween(1, 600),
            'captured_at' => now(),
        ];
    }
}
