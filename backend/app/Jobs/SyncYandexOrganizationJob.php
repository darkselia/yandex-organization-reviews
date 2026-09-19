<?php

namespace App\Jobs;

use App\Contracts\OrganizationParser;
use App\Data\ParsedOrganization;
use App\Data\ProgressCallback;
use App\Enums\ParserErrorCode;
use App\Enums\ParseRunStatus;
use App\Exceptions\OrganizationParserException;
use App\Models\Organization;
use App\Models\ParseRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Expression;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncYandexOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    /** @var list<ParserErrorCode> */
    private const RETRYABLE_ERRORS = [
        ParserErrorCode::SourceUnavailable,
        ParserErrorCode::SourceRateLimited,
        ParserErrorCode::SourceBlocked,
    ];

    public function __construct(public readonly int $parseRunId) {}

    public function handle(OrganizationParser $parser): void
    {
        if (! $this->claimParseRun()) {
            return;
        }

        $parseRun = ParseRun::query()->find($this->parseRunId);

        if ($parseRun === null || $parseRun->normalized_url === null) {
            $this->markFailed(
                'parse_run_data_missing',
                'В запуске парсинга отсутствует нормализованная ссылка.',
            );

            return;
        }

        try {
            $parsedOrganization = $parser->parse(
                $parseRun->normalized_url,
                new ProgressCallback($this->updateProgress(...)),
            );

            $this->storeResult($parsedOrganization);
        } catch (OrganizationParserException $exception) {
            if (in_array($exception->errorCode, self::RETRYABLE_ERRORS, true)
                && $this->prepareRetry($exception->errorCode->value, $exception->getMessage())) {
                $this->release($this->retryDelay());

                return;
            }

            $this->markFailed($exception->errorCode->value, $exception->getMessage());
        } catch (Throwable $exception) {
            if (! $this->prepareRetry('unexpected_error', $exception->getMessage())) {
                $this->markFailed('unexpected_error', $exception->getMessage());
            }

            throw $exception;
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed(
            'job_failed',
            $exception?->getMessage() ?? 'Queue worker не смог выполнить синхронизацию.',
        );
    }

    private function claimParseRun(): bool
    {
        return ParseRun::query()
            ->whereKey($this->parseRunId)
            ->whereIn('status', [ParseRunStatus::Queued, ParseRunStatus::Retrying])
            ->update([
                'status' => ParseRunStatus::Running,
                'attempt_count' => new Expression('attempt_count + 1'),
                'reviews_expected' => null,
                'reviews_fetched' => 0,
                'started_at' => new Expression('COALESCE(started_at, CURRENT_TIMESTAMP)'),
                'finished_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]) === 1;
    }

    private function updateProgress(int $fetched, ?int $expected): void
    {
        ParseRun::query()
            ->whereKey($this->parseRunId)
            ->where('status', ParseRunStatus::Running)
            ->update([
                'reviews_fetched' => $fetched,
                'reviews_expected' => $expected,
            ]);
    }

    private function storeResult(ParsedOrganization $parsedOrganization): void
    {
        DB::transaction(function () use ($parsedOrganization): void {
            $parseRun = ParseRun::query()
                ->whereKey($this->parseRunId)
                ->where('status', ParseRunStatus::Running)
                ->lockForUpdate()
                ->first();

            if ($parseRun === null) {
                return;
            }

            $syncedAt = now();

            $organization = Organization::query()->updateOrCreate([
                'source' => 'yandex',
                'external_id' => $parsedOrganization->externalId,
            ], [
                'source_url' => $parseRun->source_url ?? $parsedOrganization->canonicalUrl,
                'normalized_url' => $parsedOrganization->canonicalUrl,
                'name' => $parsedOrganization->name,
                'rating' => $parsedOrganization->rating,
                'ratings_count' => $parsedOrganization->ratingsCount,
                'reviews_count' => $parsedOrganization->reviewsCount,
                'last_synced_at' => $syncedAt,
            ]);

            foreach ($parsedOrganization->reviews as $review) {
                $organization->reviews()->updateOrCreate(
                    ['external_id' => $review->externalId],
                    [
                        'author_name' => $review->authorName,
                        'published_at' => $review->publishedAt,
                        'text' => $review->text,
                        'rating' => $review->rating,
                        'last_seen_at' => $syncedAt,
                    ],
                );
            }

            $organization->snapshots()->create([
                'rating' => $parsedOrganization->rating,
                'ratings_count' => $parsedOrganization->ratingsCount,
                'reviews_count' => $parsedOrganization->reviewsCount,
                'captured_at' => $syncedAt,
            ]);

            $parseRun->update([
                'organization_id' => $organization->id,
                'status' => ParseRunStatus::Succeeded,
                'finished_at' => $syncedAt,
                'error_code' => null,
                'error_message' => null,
                'diagnostics' => [
                    'source_reviews_count' => $parsedOrganization->sourceReviewsCount,
                    'stored_reviews_count' => count($parsedOrganization->reviews),
                    'skipped_reviews_count' => $parsedOrganization->skippedReviewsCount,
                ],
            ]);
        });
    }

    private function prepareRetry(string $errorCode, string $errorMessage): bool
    {
        if ($this->attemptCount() >= $this->tries) {
            return false;
        }

        return ParseRun::query()
            ->whereKey($this->parseRunId)
            ->where('status', ParseRunStatus::Running)
            ->update([
                'status' => ParseRunStatus::Retrying,
                'finished_at' => null,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]) === 1;
    }

    private function retryDelay(): int
    {
        $delays = $this->backoff();
        $attemptIndex = max(0, $this->attemptCount() - 1);

        return $delays[min($attemptIndex, count($delays) - 1)];
    }

    private function attemptCount(): int
    {
        return (int) ParseRun::query()
            ->whereKey($this->parseRunId)
            ->value('attempt_count');
    }

    private function markFailed(string $errorCode, string $errorMessage): void
    {
        $updated = ParseRun::query()
            ->whereKey($this->parseRunId)
            ->whereIn('status', [
                ParseRunStatus::Queued,
                ParseRunStatus::Running,
                ParseRunStatus::Retrying,
            ])
            ->update([
                'status' => ParseRunStatus::Failed,
                'finished_at' => now(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

        if ($updated === 1) {
            Log::warning('Yandex organization parsing failed.', [
                'parse_run_id' => $this->parseRunId,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);
        }
    }
}
