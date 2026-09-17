<?php

namespace Tests\Feature;

use App\Enums\ParseRunStatus;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\ParseRun;
use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_review_id_is_unique_inside_an_organization(): void
    {
        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();

        Review::factory()->for($firstOrganization)->create(['external_id' => 'review-1']);
        Review::factory()->for($secondOrganization)->create(['external_id' => 'review-1']);

        $this->expectException(QueryException::class);

        Review::factory()->for($firstOrganization)->create(['external_id' => 'review-1']);
    }

    public function test_normalized_organization_url_is_unique(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(QueryException::class);

        Organization::factory()->create([
            'normalized_url' => $organization->normalized_url,
        ]);
    }

    public function test_deleting_an_organization_deletes_its_related_data(): void
    {
        $organization = Organization::factory()->create();
        $review = Review::factory()->for($organization)->create();
        $parseRun = ParseRun::factory()->for($organization)->create();
        $snapshot = OrganizationSnapshot::factory()->for($organization)->create();

        $organization->delete();

        $this->assertModelMissing($review);
        $this->assertModelMissing($parseRun);
        $this->assertModelMissing($snapshot);
    }

    public function test_domain_values_are_cast_to_expected_types(): void
    {
        $organization = Organization::factory()->create([
            'rating' => 4.8,
            'last_synced_at' => now(),
        ]);
        $parseRun = ParseRun::factory()->for($organization)->create([
            'status' => ParseRunStatus::Failed,
            'diagnostics' => ['http_status' => 429],
        ]);

        $this->assertSame('4.80', $organization->rating);
        $this->assertNotNull($organization->last_synced_at);
        $this->assertSame(ParseRunStatus::Failed, $parseRun->status);
        $this->assertSame(['http_status' => 429], $parseRun->diagnostics);
        $this->assertSame($parseRun->id, $organization->latestParseRun->id);
    }
}
