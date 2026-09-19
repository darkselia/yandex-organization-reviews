<?php

namespace App\Actions\Organizations;

use App\Data\NormalizedYandexUrl;
use App\Enums\ParseRunStatus;
use App\Jobs\SyncYandexOrganizationJob;
use App\Models\ParseRun;
use Illuminate\Support\Facades\DB;

class ConnectOrganization
{
    /** @var list<ParseRunStatus> */
    private const ACTIVE_STATUSES = [
        ParseRunStatus::Queued,
        ParseRunStatus::Running,
        ParseRunStatus::Retrying,
    ];

    public function execute(string $sourceUrl, NormalizedYandexUrl $normalizedUrl): ParseRun
    {
        return DB::transaction(function () use ($sourceUrl, $normalizedUrl): ParseRun {
            $parseRun = ParseRun::query()
                ->where('source_external_id', $normalizedUrl->externalId)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->latest()
                ->first();

            if ($parseRun === null) {
                $parseRun = ParseRun::query()->create([
                    'organization_id' => null,
                    'source_url' => trim($sourceUrl),
                    'normalized_url' => $normalizedUrl->url,
                    'source_external_id' => $normalizedUrl->externalId,
                    'status' => ParseRunStatus::Queued,
                    'attempt_count' => 0,
                    'reviews_fetched' => 0,
                ]);

                SyncYandexOrganizationJob::dispatch($parseRun->id)->afterCommit();
            }

            return $parseRun;
        });
    }
}
