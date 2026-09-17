<?php

namespace App\Actions\Organizations;

use App\Data\NormalizedYandexUrl;
use App\Data\OrganizationConnection;
use App\Enums\ParseRunStatus;
use App\Jobs\SyncYandexOrganizationJob;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class ConnectOrganization
{
    /** @var list<ParseRunStatus> */
    private const ACTIVE_STATUSES = [
        ParseRunStatus::Queued,
        ParseRunStatus::Running,
        ParseRunStatus::Retrying,
    ];

    public function execute(string $sourceUrl, NormalizedYandexUrl $normalizedUrl): OrganizationConnection
    {
        return DB::transaction(function () use ($sourceUrl, $normalizedUrl): OrganizationConnection {
            $organization = Organization::query()->firstOrCreate(
                [
                    'source' => 'yandex',
                    'external_id' => $normalizedUrl->externalId,
                ],
                [
                    'source_url' => trim($sourceUrl),
                    'normalized_url' => $normalizedUrl->url,
                    'ratings_count' => 0,
                    'reviews_count' => 0,
                ],
            );

            if (! $organization->wasRecentlyCreated) {
                $organization->update([
                    'source_url' => trim($sourceUrl),
                    'normalized_url' => $normalizedUrl->url,
                ]);
            }

            $parseRun = $organization->parseRuns()
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->latest()
                ->first();

            if ($parseRun === null) {
                $parseRun = $organization->parseRuns()->create([
                    'status' => ParseRunStatus::Queued,
                    'attempt_count' => 0,
                    'reviews_fetched' => 0,
                ]);

                SyncYandexOrganizationJob::dispatch($organization->id, $parseRun->id)->afterCommit();
            }

            return new OrganizationConnection($organization, $parseRun);
        });
    }
}
