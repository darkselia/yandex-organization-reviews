<?php

namespace Tests\Feature;

use App\Enums\ParseRunStatus;
use App\Jobs\SyncYandexOrganizationJob;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_connection_requires_authentication(): void
    {
        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/cafe/123456789/',
        ])->assertUnauthorized();
    }

    public function test_yandex_organization_url_is_required_and_validated(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/organizations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);

        $this->postJson('/api/organizations', [
            'url' => 'https://example.com/maps/org/cafe/123456789/',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Переданные данные некорректны.')
            ->assertJsonValidationErrors(['url']);

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/search/?text=cafe',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_full_url_is_normalized_and_parse_job_is_queued(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/api/organizations', [
            'url' => ' http://www.yandex.ru/maps/org/cafe/123456789/?utm_source=share#reviews ',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.organization.source_url', 'http://www.yandex.ru/maps/org/cafe/123456789/?utm_source=share#reviews')
            ->assertJsonPath('data.organization.name', null)
            ->assertJsonPath('data.organization.ratings_count', 0)
            ->assertJsonPath('data.parse_run.status', ParseRunStatus::Queued->value)
            ->assertJsonPath('data.parse_run.progress_percent', null)
            ->assertJsonPath('data.parse_run.error', null);

        $organization = Organization::query()->sole();
        $parseRun = $organization->parseRuns()->sole();

        $this->assertSame('123456789', $organization->external_id);
        $this->assertSame('https://yandex.ru/maps/org/cafe/123456789', $organization->normalized_url);

        Queue::assertPushed(
            SyncYandexOrganizationJob::class,
            fn (SyncYandexOrganizationJob $job): bool => $job->organizationId === $organization->id
                && $job->parseRunId === $parseRun->id,
        );
    }

    public function test_short_url_is_resolved_before_organization_is_created(): void
    {
        Queue::fake();
        Http::fake([
            'https://yandex.ru/maps/-/short-code' => Http::response('', 302, [
                'Location' => 'https://www.yandex.ru/maps/org/cafe/987654321/?ll=1,2&utm_source=share',
            ]),
        ]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/-/short-code',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.parse_run.status', ParseRunStatus::Queued->value);

        $this->assertDatabaseHas('organizations', [
            'source_url' => 'https://yandex.ru/maps/-/short-code',
            'normalized_url' => 'https://yandex.ru/maps/org/cafe/987654321',
            'external_id' => '987654321',
        ]);

        Http::assertSentCount(1);
    }

    public function test_short_url_cannot_redirect_outside_yandex(): void
    {
        Queue::fake();
        Http::fake([
            'https://yandex.ru/maps/-/unsafe' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/private',
            ]),
        ]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/-/unsafe',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Не удалось обработать ссылку организации.')
            ->assertJsonValidationErrors(['url']);

        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('parse_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_active_parse_run_is_reused_for_repeated_url(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $firstResponse = $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/cafe/123456789/',
        ])->assertAccepted();

        $secondResponse = $this->postJson('/api/organizations', [
            'url' => 'https://www.yandex.ru/maps/org/123456789?utm_source=copy',
        ])->assertAccepted();

        $this->assertSame(
            $firstResponse->json('data.organization.id'),
            $secondResponse->json('data.organization.id'),
        );
        $this->assertSame(
            $firstResponse->json('data.parse_run.id'),
            $secondResponse->json('data.parse_run.id'),
        );
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('parse_runs', 1);
        Queue::assertPushed(SyncYandexOrganizationJob::class, 1);
    }

    public function test_completed_parse_run_allows_a_new_synchronization(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/cafe/123456789',
        ])->assertAccepted();

        Organization::query()->sole()->parseRuns()->sole()->update([
            'status' => ParseRunStatus::Succeeded,
            'finished_at' => now(),
        ]);

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/cafe/123456789/',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.parse_run.status', ParseRunStatus::Queued->value);

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('parse_runs', 2);
        Queue::assertPushed(SyncYandexOrganizationJob::class, 2);
    }
}
