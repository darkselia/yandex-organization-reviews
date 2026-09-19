<?php

namespace Database\Factories;

use App\Enums\ParseRunStatus;
use App\Models\Organization;
use App\Models\ParseRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ParseRun> */
class ParseRunFactory extends Factory
{
    public function definition(): array
    {
        $reviewsCount = fake()->numberBetween(1, 600);

        return [
            'organization_id' => Organization::factory(),
            'source_url' => 'https://yandex.ru/maps/org/test/123456789/',
            'normalized_url' => 'https://yandex.ru/maps/org/test/123456789',
            'source_external_id' => '123456789',
            'status' => ParseRunStatus::Succeeded,
            'attempt_count' => 1,
            'reviews_expected' => $reviewsCount,
            'reviews_fetched' => $reviewsCount,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error_code' => null,
            'error_message' => null,
            'diagnostics' => null,
        ];
    }
}
