<?php

namespace Tests\Feature;

use App\Enums\ParseRunStatus;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_routes_require_authentication(): void
    {
        $this->getJson('/api/organizations')->assertUnauthorized();
        $this->getJson('/api/organizations/1')->assertUnauthorized();
        $this->getJson('/api/organizations/1/reviews')->assertUnauthorized();
        $this->getJson('/api/parse-runs/1')->assertUnauthorized();
    }

    public function test_list_and_details_include_latest_parse_run(): void
    {
        $this->actingAs(User::factory()->create());
        $olderOrganization = Organization::factory()->create([
            'name' => 'Старая организация',
            'updated_at' => now()->subHour(),
        ]);
        $newerOrganization = Organization::factory()->create([
            'name' => 'Новая организация',
            'updated_at' => now(),
        ]);
        ParseRun::factory()->for($newerOrganization)->create([
            'status' => ParseRunStatus::Failed,
            'error_code' => 'old_error',
            'error_message' => 'Старая ошибка',
            'created_at' => now()->subMinute(),
        ]);
        $latestParseRun = ParseRun::factory()->for($newerOrganization)->create([
            'status' => ParseRunStatus::Running,
            'reviews_fetched' => 25,
            'reviews_expected' => 100,
            'finished_at' => null,
            'created_at' => now(),
        ]);

        $this->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerOrganization->id)
            ->assertJsonPath('data.0.latest_parse_run.id', $latestParseRun->id)
            ->assertJsonPath('data.0.latest_parse_run.status', ParseRunStatus::Running->value)
            ->assertJsonPath('data.0.latest_parse_run.progress_percent', 25)
            ->assertJsonPath('data.1.id', $olderOrganization->id)
            ->assertJsonPath('data.1.latest_parse_run', null);

        $this->getJson("/api/organizations/{$newerOrganization->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $newerOrganization->id)
            ->assertJsonPath('data.name', 'Новая организация')
            ->assertJsonPath('data.latest_parse_run.id', $latestParseRun->id);
    }

    public function test_reviews_are_sorted_and_paginated_by_fifty(): void
    {
        $this->actingAs(User::factory()->create());
        $organization = Organization::factory()->create();

        for ($number = 1; $number <= 55; $number++) {
            Review::factory()->for($organization)->create([
                'external_id' => "review-{$number}",
                'author_name' => "Автор {$number}",
                'published_at' => now()->subMinutes(55 - $number),
            ]);
        }

        $firstPage = $this->getJson("/api/organizations/{$organization->id}/reviews?page=1")
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('data.0.external_id', 'review-55')
            ->assertJsonPath('data.49.external_id', 'review-6')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55);

        $this->assertSame(50, count($firstPage->json('data')));

        $this->getJson("/api/organizations/{$organization->id}/reviews?page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.external_id', 'review-5')
            ->assertJsonPath('data.4.external_id', 'review-1')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_parse_run_endpoint_serializes_progress_and_error(): void
    {
        $this->actingAs(User::factory()->create());
        $organization = Organization::factory()->create();
        $running = ParseRun::factory()->for($organization)->create([
            'status' => ParseRunStatus::Running,
            'attempt_count' => 2,
            'reviews_fetched' => 75,
            'reviews_expected' => 300,
            'finished_at' => null,
        ]);
        $failed = ParseRun::factory()->for($organization)->create([
            'status' => ParseRunStatus::Failed,
            'error_code' => 'source_blocked',
            'error_message' => 'Яндекс Карты заблокировали запрос.',
        ]);

        $this->getJson("/api/parse-runs/{$running->id}")
            ->assertOk()
            ->assertJsonPath('data.status', ParseRunStatus::Running->value)
            ->assertJsonPath('data.attempt_count', 2)
            ->assertJsonPath('data.reviews_fetched', 75)
            ->assertJsonPath('data.reviews_expected', 300)
            ->assertJsonPath('data.progress_percent', 25)
            ->assertJsonPath('data.error', null);

        $this->getJson("/api/parse-runs/{$failed->id}")
            ->assertOk()
            ->assertJsonPath('data.status', ParseRunStatus::Failed->value)
            ->assertJsonPath('data.error.code', 'source_blocked')
            ->assertJsonPath('data.error.message', 'Яндекс Карты заблокировали запрос.');
    }

    public function test_missing_resources_return_consistent_json_error(): void
    {
        $this->actingAs(User::factory()->create());

        $expectedError = [
            'message' => 'Ресурс не найден.',
            'errors' => [],
        ];

        $this->getJson('/api/organizations/999999')
            ->assertNotFound()
            ->assertExactJson($expectedError);
        $this->getJson('/api/organizations/999999/reviews')
            ->assertNotFound()
            ->assertExactJson($expectedError);
        $this->getJson('/api/parse-runs/999999')
            ->assertNotFound()
            ->assertExactJson($expectedError);
    }
}
