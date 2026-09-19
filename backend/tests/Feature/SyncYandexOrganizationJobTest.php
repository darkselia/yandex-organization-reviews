<?php

namespace Tests\Feature;

use App\Contracts\OrganizationParser;
use App\Data\ParsedOrganization;
use App\Data\ParsedReview;
use App\Data\ProgressCallback;
use App\Enums\ParserErrorCode;
use App\Enums\ParseRunStatus;
use App\Exceptions\OrganizationParserException;
use App\Jobs\SyncYandexOrganizationJob;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncYandexOrganizationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_parser_result_reports_progress_and_does_not_run_twice(): void
    {
        $organization = Organization::factory()->create([
            'external_id' => '123456789',
            'normalized_url' => 'https://yandex.ru/maps/org/old_slug/123456789',
            'name' => null,
            'rating' => null,
            'ratings_count' => 0,
            'reviews_count' => 0,
            'last_synced_at' => null,
        ]);
        $parseRun = ParseRun::factory()->create([
            'organization_id' => null,
            'source_url' => 'https://yandex.ru/maps/org/old_slug/123456789',
            'normalized_url' => 'https://yandex.ru/maps/org/old_slug/123456789',
            'status' => ParseRunStatus::Queued,
            'attempt_count' => 0,
            'reviews_expected' => null,
            'reviews_fetched' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);
        Review::factory()->for($organization)->create([
            'external_id' => 'review-1',
            'author_name' => 'Старое имя',
            'rating' => 1,
        ]);
        $parser = new class implements OrganizationParser
        {
            public int $calls = 0;

            public ?string $receivedUrl = null;

            public function parse(string $url, ProgressCallback $progress): ParsedOrganization
            {
                $this->calls++;
                $this->receivedUrl = $url;
                $progress(1, 2);
                $progress(2, 2);

                return new ParsedOrganization(
                    externalId: '123456789',
                    canonicalUrl: 'https://yandex.ru/maps/org/testovaya_kofeynya/123456789',
                    name: 'Тестовая кофейня',
                    rating: null,
                    ratingsCount: 0,
                    reviewsCount: 2,
                    reviews: [
                        new ParsedReview(
                            externalId: 'review-1',
                            authorName: 'Анна',
                            publishedAt: new DateTimeImmutable('2026-08-10T09:30:00+00:00'),
                            text: 'Обновлённый отзыв',
                            rating: 5,
                        ),
                        new ParsedReview(
                            externalId: 'review-2',
                            authorName: 'Пользователь Яндекса',
                            publishedAt: new DateTimeImmutable('2026-08-11T10:00:00+00:00'),
                            text: null,
                            rating: 4,
                        ),
                    ],
                    sourceReviewsCount: 2,
                    skippedReviewsCount: 0,
                );
            }
        };
        $job = new SyncYandexOrganizationJob($parseRun->id);

        $job->handle($parser);
        $job->handle($parser);

        $this->assertSame(1, $parser->calls);
        $this->assertSame(
            'https://yandex.ru/maps/org/old_slug/123456789',
            $parser->receivedUrl,
        );
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'normalized_url' => 'https://yandex.ru/maps/org/testovaya_kofeynya/123456789',
            'name' => 'Тестовая кофейня',
            'rating' => null,
            'ratings_count' => 0,
            'reviews_count' => 2,
        ]);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Анна',
            'text' => 'Обновлённый отзыв',
            'rating' => 5,
        ]);
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('organization_snapshots', [
            'organization_id' => $organization->id,
            'parse_run_id' => $parseRun->id,
            'rating' => null,
            'ratings_count' => 0,
            'reviews_count' => 2,
        ]);
        $this->assertDatabaseCount('organization_snapshots', 1);

        $parseRun->refresh();
        $this->assertSame($organization->id, $parseRun->organization_id);
        $this->assertSame(ParseRunStatus::Succeeded, $parseRun->status);
        $this->assertSame(1, $parseRun->attempt_count);
        $this->assertSame(2, $parseRun->reviews_expected);
        $this->assertSame(2, $parseRun->reviews_fetched);
        $this->assertNotNull($parseRun->started_at);
        $this->assertNotNull($parseRun->finished_at);
        $this->assertNull($parseRun->error_code);
        $this->assertSame([
            'source_reviews_count' => 2,
            'stored_reviews_count' => 2,
            'skipped_reviews_count' => 0,
        ], $parseRun->diagnostics);
    }

    public function test_repeated_sync_creates_linked_snapshots_with_changed_counters(): void
    {
        $firstRun = ParseRun::factory()->create([
            'organization_id' => null,
            'status' => ParseRunStatus::Queued,
            'attempt_count' => 0,
        ]);
        $parser = new class implements OrganizationParser
        {
            public int $calls = 0;

            public function parse(string $url, ProgressCallback $progress): ParsedOrganization
            {
                $this->calls++;
                $isSecondSync = $this->calls === 2;

                return new ParsedOrganization(
                    externalId: '123456789',
                    canonicalUrl: 'https://yandex.ru/maps/org/test/123456789',
                    name: 'Тестовая организация',
                    rating: $isSecondSync ? 4.8 : 4.5,
                    ratingsCount: $isSecondSync ? 112 : 100,
                    reviewsCount: $isSecondSync ? 12 : 10,
                    reviews: [
                        new ParsedReview(
                            externalId: 'review-1',
                            authorName: 'Анна',
                            publishedAt: new DateTimeImmutable('2026-08-10T09:30:00+00:00'),
                            text: $isSecondSync ? 'Обновлённый текст' : 'Первый текст',
                            rating: $isSecondSync ? 5 : 4,
                        ),
                    ],
                    sourceReviewsCount: 1,
                    skippedReviewsCount: 0,
                );
            }
        };

        (new SyncYandexOrganizationJob($firstRun->id))->handle($parser);

        $secondRun = ParseRun::factory()->create([
            'organization_id' => null,
            'status' => ParseRunStatus::Queued,
            'attempt_count' => 0,
        ]);
        (new SyncYandexOrganizationJob($secondRun->id))->handle($parser);

        $organization = Organization::query()
            ->where('external_id', '123456789')
            ->firstOrFail();

        $this->assertSame(1, Organization::query()->count());
        $this->assertSame('4.80', $organization->rating);
        $this->assertSame(112, $organization->ratings_count);
        $this->assertSame(12, $organization->reviews_count);
        $this->assertDatabaseCount('organization_snapshots', 2);
        $this->assertDatabaseHas('organization_snapshots', [
            'organization_id' => $organization->id,
            'parse_run_id' => $firstRun->id,
            'rating' => 4.5,
            'ratings_count' => 100,
            'reviews_count' => 10,
        ]);
        $this->assertDatabaseHas('organization_snapshots', [
            'organization_id' => $organization->id,
            'parse_run_id' => $secondRun->id,
            'rating' => 4.8,
            'ratings_count' => 112,
            'reviews_count' => 12,
        ]);
        $this->assertSame($firstRun->id, $firstRun->snapshot()->firstOrFail()->parse_run_id);
        $this->assertSame($secondRun->id, $secondRun->snapshot()->firstOrFail()->parse_run_id);
    }

    public function test_permanent_parser_error_is_saved_without_retry(): void
    {
        $parseRun = ParseRun::factory()->create([
            'organization_id' => null,
            'status' => ParseRunStatus::Queued,
            'attempt_count' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);
        $parser = new class implements OrganizationParser
        {
            public function parse(string $url, ProgressCallback $progress): ParsedOrganization
            {
                throw new OrganizationParserException(
                    ParserErrorCode::SourceSchemaChanged,
                    'Структура ответа Яндекс Карт изменилась.',
                );
            }
        };

        $job = (new SyncYandexOrganizationJob($parseRun->id))
            ->withFakeQueueInteractions();
        $job->handle($parser);

        $parseRun->refresh();
        $this->assertSame(ParseRunStatus::Failed, $parseRun->status);
        $this->assertSame(1, $parseRun->attempt_count);
        $this->assertSame(ParserErrorCode::SourceSchemaChanged->value, $parseRun->error_code);
        $this->assertSame(
            'Структура ответа Яндекс Карт изменилась.',
            $parseRun->error_message,
        );
        $this->assertNotNull($parseRun->started_at);
        $this->assertNotNull($parseRun->finished_at);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('organization_snapshots', 0);
        $this->assertDatabaseCount('organizations', 0);
        $job->assertNotReleased();
    }

    public function test_transient_parser_error_is_retried_twice_and_then_failed(): void
    {
        $parseRun = ParseRun::factory()->create([
            'organization_id' => null,
            'status' => ParseRunStatus::Queued,
            'attempt_count' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);
        $parser = new class implements OrganizationParser
        {
            public int $calls = 0;

            public function parse(string $url, ProgressCallback $progress): ParsedOrganization
            {
                $this->calls++;

                throw new OrganizationParserException(
                    ParserErrorCode::SourceRateLimited,
                    'Яндекс Карты временно ограничили частоту запросов.',
                );
            }
        };

        $firstAttempt = (new SyncYandexOrganizationJob($parseRun->id))
            ->withFakeQueueInteractions();
        $firstAttempt->handle($parser);
        $firstAttempt->assertReleased(60);

        $parseRun->refresh();
        $this->assertSame(ParseRunStatus::Retrying, $parseRun->status);
        $this->assertSame(1, $parseRun->attempt_count);
        $this->assertSame(ParserErrorCode::SourceRateLimited->value, $parseRun->error_code);

        $secondAttempt = (new SyncYandexOrganizationJob($parseRun->id))
            ->withFakeQueueInteractions();
        $secondAttempt->handle($parser);
        $secondAttempt->assertReleased(300);

        $parseRun->refresh();
        $this->assertSame(ParseRunStatus::Retrying, $parseRun->status);
        $this->assertSame(2, $parseRun->attempt_count);

        $thirdAttempt = (new SyncYandexOrganizationJob($parseRun->id))
            ->withFakeQueueInteractions();
        $thirdAttempt->handle($parser);
        $thirdAttempt->assertNotReleased();

        $parseRun->refresh();
        $this->assertSame(ParseRunStatus::Failed, $parseRun->status);
        $this->assertSame(3, $parseRun->attempt_count);
        $this->assertSame(ParserErrorCode::SourceRateLimited->value, $parseRun->error_code);
        $this->assertSame(3, $parser->calls);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_retry_can_finish_successfully_without_duplicate_reviews(): void
    {
        $parseRun = ParseRun::factory()->create([
            'organization_id' => null,
            'status' => ParseRunStatus::Queued,
            'attempt_count' => 0,
        ]);
        $parser = new class implements OrganizationParser
        {
            public int $calls = 0;

            public function parse(string $url, ProgressCallback $progress): ParsedOrganization
            {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new OrganizationParserException(
                        ParserErrorCode::SourceUnavailable,
                        'Источник временно недоступен.',
                    );
                }

                return new ParsedOrganization(
                    externalId: '123456789',
                    canonicalUrl: 'https://yandex.ru/maps/org/test/123456789',
                    name: 'Тестовая организация',
                    rating: 5.0,
                    ratingsCount: 1,
                    reviewsCount: 1,
                    reviews: [
                        new ParsedReview(
                            externalId: 'review-1',
                            authorName: 'Анна',
                            publishedAt: new DateTimeImmutable('2026-08-10T09:30:00+00:00'),
                            text: 'Отлично',
                            rating: 5,
                        ),
                    ],
                    sourceReviewsCount: 1,
                    skippedReviewsCount: 0,
                );
            }
        };

        $firstAttempt = (new SyncYandexOrganizationJob($parseRun->id))
            ->withFakeQueueInteractions();
        $firstAttempt->handle($parser);
        $firstAttempt->assertReleased(60);

        $secondAttempt = (new SyncYandexOrganizationJob($parseRun->id))
            ->withFakeQueueInteractions();
        $secondAttempt->handle($parser);
        $secondAttempt->assertNotReleased();

        $parseRun->refresh();
        $this->assertSame(ParseRunStatus::Succeeded, $parseRun->status);
        $this->assertSame(2, $parseRun->attempt_count);
        $this->assertSame(2, $parser->calls);
        $this->assertNotNull($parseRun->organization_id);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseCount('organization_snapshots', 1);
    }
}
