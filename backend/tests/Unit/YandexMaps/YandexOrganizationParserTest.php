<?php

namespace Tests\Unit\YandexMaps;

use App\Contracts\OrganizationParser;
use App\Data\ProgressCallback;
use App\Enums\ParserErrorCode;
use App\Exceptions\OrganizationParserException;
use App\Services\YandexMaps\YandexOrganizationParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexOrganizationParserTest extends TestCase
{
    private const ORGANIZATION_URL = 'https://yandex.ru/maps/org/testovaya_kofeynya/123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_parser_contract_is_bound_to_yandex_implementation(): void
    {
        $this->assertInstanceOf(
            YandexOrganizationParser::class,
            $this->app->make(OrganizationParser::class),
        );
    }

    public function test_it_loads_all_pages_and_reports_progress(): void
    {
        Http::fakeSequence()
            ->push($this->htmlFixture('reviews-page-1.json'), 200, ['Content-Type' => 'text/html'])
            ->push($this->htmlFixture('reviews-page-2.json'), 200, ['Content-Type' => 'text/html']);
        $updates = [];
        $progress = new ProgressCallback(
            function (int $fetched, ?int $expected) use (&$updates): void {
                $updates[] = [$fetched, $expected];
            },
        );

        $organization = $this->app->make(OrganizationParser::class)
            ->parse(self::ORGANIZATION_URL, $progress);

        $this->assertSame('123456789', $organization->externalId);
        $this->assertSame('Тестовая кофейня', $organization->name);
        $this->assertSame(4.8, $organization->rating);
        $this->assertSame(125, $organization->ratingsCount);
        $this->assertSame(3, $organization->reviewsCount);
        $this->assertSame(3, $organization->sourceReviewsCount);
        $this->assertSame(0, $organization->skippedReviewsCount);
        $this->assertSame(['review-1', 'review-2', 'review-3'], array_map(
            static fn ($review): string => $review->externalId,
            $organization->reviews,
        ));
        $this->assertSame([[2, 3], [3, 3]], $updates);
        Http::assertSentCount(2);
    }

    public function test_it_detects_a_repeated_reviews_page(): void
    {
        $repeatedPage = json_decode(
            $this->fixture('reviews-page-1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $repeatedPage['stack'][0]['results']['items'][0]['reviewResults']['params']['page'] = 2;
        $repeatedPage['stack'][0]['results']['items'][0]['reviewResults']['params']['offset'] = 2;

        Http::fakeSequence()
            ->push($this->htmlFixture('reviews-page-1.json'), 200, ['Content-Type' => 'text/html'])
            ->push($this->html(json_encode($repeatedPage, JSON_THROW_ON_ERROR)), 200, [
                'Content-Type' => 'text/html',
            ]);

        try {
            $this->app->make(OrganizationParser::class)
                ->parse(self::ORGANIZATION_URL, new ProgressCallback);
            $this->fail('Repeated page did not produce a parser exception.');
        } catch (OrganizationParserException $exception) {
            $this->assertSame(ParserErrorCode::InvalidSourceData, $exception->errorCode);
        }
    }

    public function test_it_stops_after_six_hundred_reviews(): void
    {
        $sequence = Http::fakeSequence();

        for ($page = 1; $page <= 12; $page++) {
            $state = json_decode(
                $this->fixture('reviews-page-1.json'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $item = &$state['stack'][0]['results']['items'][0];
            $item['ratingData']['reviewCount'] = 1000;
            $item['reviewResults']['params'] = [
                'offset' => ($page - 1) * 50,
                'limit' => 50,
                'count' => 1000,
                'loadedReviewsCount' => $page * 50,
                'page' => $page,
                'totalPages' => 20,
                'reviewsRemained' => 1000 - ($page * 50),
            ];
            $item['reviewResults']['reviews'] = [];

            for ($review = 1; $review <= 50; $review++) {
                $number = (($page - 1) * 50) + $review;
                $item['reviewResults']['reviews'][] = [
                    'reviewId' => "review-{$number}",
                    'businessId' => '123456789',
                    'author' => ['name' => "Автор {$number}"],
                    'text' => "Отзыв {$number}",
                    'rating' => 5,
                    'updatedTime' => '2026-08-10T09:30:00.000Z',
                ];
            }

            $sequence->push($this->html(json_encode($state, JSON_THROW_ON_ERROR)), 200, [
                'Content-Type' => 'text/html',
            ]);
        }

        $organization = $this->app->make(OrganizationParser::class)
            ->parse(self::ORGANIZATION_URL, new ProgressCallback);

        $this->assertCount(600, $organization->reviews);
        $this->assertSame(1000, $organization->reviewsCount);
        Http::assertSentCount(12);
    }

    public function test_it_accepts_fewer_unique_reviews_when_source_marks_last_page(): void
    {
        $firstPage = json_decode(
            $this->fixture('reviews-page-1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $lastPage = json_decode(
            $this->fixture('reviews-page-2.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $firstItem = &$firstPage['stack'][0]['results']['items'][0];
        $item = &$lastPage['stack'][0]['results']['items'][0];
        $firstItem['ratingData']['reviewCount'] = 4;
        $firstItem['reviewResults']['params']['count'] = 4;
        $item['ratingData']['reviewCount'] = 4;
        $item['reviewResults']['params']['count'] = 4;

        Http::fakeSequence()
            ->push($this->html(json_encode($firstPage, JSON_THROW_ON_ERROR)), 200, [
                'Content-Type' => 'text/html',
            ])
            ->push($this->html(json_encode($lastPage, JSON_THROW_ON_ERROR)), 200, [
                'Content-Type' => 'text/html',
            ]);

        $organization = $this->app->make(OrganizationParser::class)
            ->parse(self::ORGANIZATION_URL, new ProgressCallback);

        $this->assertSame(4, $organization->reviewsCount);
        $this->assertCount(3, $organization->reviews);
        Http::assertSentCount(2);
    }

    private function htmlFixture(string $fixture): string
    {
        return $this->html($this->fixture($fixture));
    }

    private function fixture(string $fixture): string
    {
        $json = file_get_contents(base_path("tests/Fixtures/YandexMaps/{$fixture}"));

        $this->assertIsString($json);

        return $json;
    }

    private function html(string $json): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head><body>'
            .'<script type="application/json" class="state-view">'.$json.'</script>'
            .'</body></html>';
    }
}
