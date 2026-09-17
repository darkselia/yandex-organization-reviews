<?php

namespace App\Jobs;

use App\Enums\ParseRunStatus;
use App\Models\ParseRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncYandexOrganizationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $parseRunId,
    ) {}

    public function handle(): void
    {
        $parseRun = ParseRun::query()->find($this->parseRunId);

        if ($parseRun === null || $parseRun->status !== ParseRunStatus::Queued) {
            return;
        }

        $parseRun->update([
            'status' => ParseRunStatus::Failed,
            'attempt_count' => $parseRun->attempt_count + 1,
            'started_at' => now(),
            'finished_at' => now(),
            'error_code' => 'parser_not_implemented',
            'error_message' => 'Парсер Яндекс Карт будет подключён на следующем этапе.',
        ]);
    }
}
