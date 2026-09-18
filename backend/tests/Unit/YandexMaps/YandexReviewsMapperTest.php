<?php

namespace Tests\Unit\YandexMaps;

use App\Enums\ParserErrorCode;
use App\Exceptions\OrganizationParserException;
use App\Services\YandexMaps\YandexReviewsMapper;
use Tests\TestCase;

class YandexReviewsMapperTest extends TestCase
{
    private const ORGANIZATION_URL = 'https://yandex.ru/maps/org/old_slug/123456789';

    public function test_it_maps_embedded_yandex_state_to_typed_page(): void
    {
        $page = (new YandexReviewsMapper)->map(
            $this->htmlFixture('reviews-page-1.json'),
            self::ORGANIZATION_URL,
            1,
        );

        $this->assertNotNull($page);
        $this->assertSame('123456789', $page->externalId);
        $this->assertSame(
            'https://yandex.ru/maps/org/testovaya_kofeynya/123456789',
            $page->canonicalUrl,
        );
        $this->assertSame('Тестовая кофейня', $page->name);
        $this->assertSame(4.8, $page->rating);
        $this->assertSame(125, $page->ratingsCount);
        $this->assertSame(3, $page->reviewsCount);
        $this->assertSame(3, $page->availableReviewsCount);
        $this->assertSame(1, $page->reviewsRemaining);
        $this->assertCount(2, $page->reviews);
        $this->assertSame('Анна', $page->reviews[0]->authorName);
        $this->assertSame('2026-08-10T09:30:00+00:00', $page->reviews[0]->publishedAt->format('c'));
    }

    public function test_review_text_can_be_null(): void
    {
        $page = (new YandexReviewsMapper)->map(
            $this->htmlFixture('reviews-page-2.json'),
            self::ORGANIZATION_URL,
            2,
        );

        $this->assertNotNull($page);
        $this->assertNull($page->reviews[0]->text);
        $this->assertSame('Пользователь Яндекса', $page->reviews[0]->authorName);
    }

    public function test_review_without_rating_is_not_returned_as_valid_review(): void
    {
        $state = json_decode(
            file_get_contents(base_path('tests/Fixtures/YandexMaps/reviews-page-2.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $reviewWithoutRating = $state['stack'][0]['results']['items'][0]['reviewResults']['reviews'][0];
        $reviewWithoutRating['reviewId'] = 'review-without-rating';
        $reviewWithoutRating['rating'] = 0;
        $state['stack'][0]['results']['items'][0]['reviewResults']['reviews'][] = $reviewWithoutRating;

        $page = (new YandexReviewsMapper)->map(
            '<script type="application/json" class="state-view">'
                .json_encode($state, JSON_THROW_ON_ERROR)
                .'</script>',
            self::ORGANIZATION_URL,
            2,
        );

        $this->assertNotNull($page);
        $this->assertSame(2, $page->sourceReviewsCount);
        $this->assertCount(1, $page->reviews);
    }

    public function test_missing_required_section_reports_schema_change(): void
    {
        try {
            (new YandexReviewsMapper)->map(
                $this->htmlFixture('schema-changed.json'),
                self::ORGANIZATION_URL,
                1,
            );
            $this->fail('Changed schema did not produce a parser exception.');
        } catch (OrganizationParserException $exception) {
            $this->assertSame(ParserErrorCode::SourceSchemaChanged, $exception->errorCode);
        }
    }

    public function test_missing_state_script_reports_schema_change(): void
    {
        try {
            (new YandexReviewsMapper)->map(
                '<!doctype html><html><body>no state</body></html>',
                self::ORGANIZATION_URL,
                1,
            );
            $this->fail('Missing state did not produce a parser exception.');
        } catch (OrganizationParserException $exception) {
            $this->assertSame(ParserErrorCode::SourceSchemaChanged, $exception->errorCode);
        }
    }

    private function htmlFixture(string $fixture): string
    {
        $json = file_get_contents(base_path("tests/Fixtures/YandexMaps/{$fixture}"));

        $this->assertIsString($json);

        return '<!doctype html><html><head><meta charset="utf-8"></head><body>'
            .'<script type="application/json" class="state-view">'.$json.'</script>'
            .'</body></html>';
    }
}
