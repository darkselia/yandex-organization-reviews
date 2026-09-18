<?php

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationParser;
use App\Data\ParsedOrganization;
use App\Data\ProgressCallback;
use App\Data\YandexMaps\YandexReviewsPage;
use App\Enums\ParserErrorCode;
use App\Exceptions\OrganizationParserException;

class YandexOrganizationParser implements OrganizationParser
{
    private const MAX_REVIEWS = 600;

    public function __construct(
        private readonly YandexMapsClient $client,
        private readonly YandexReviewsMapper $mapper,
    ) {}

    public function parse(string $url, ProgressCallback $progress): ParsedOrganization
    {
        $firstPage = null;
        $reviewsById = [];
        $processedReviewIds = [];
        $pageSignatures = [];
        $expectedReviews = null;
        $maximumPages = 1;
        $sourceExhausted = false;

        for ($pageNumber = 1; $pageNumber <= $maximumPages; $pageNumber++) {
            $html = $this->client->fetchReviewsPage($url, $pageNumber);
            $page = $this->mapper->map($html, $url, $pageNumber);

            if ($page === null) {
                $sourceExhausted = true;

                break;
            }

            if ($firstPage === null) {
                $firstPage = $page;
                $expectedReviews = min($page->availableReviewsCount, self::MAX_REVIEWS);
                $maximumPages = max(1, min(
                    (int) ceil($expectedReviews / $page->pageSize),
                    (int) ceil(self::MAX_REVIEWS / $page->pageSize),
                    max(1, $page->totalPages),
                ));
            } elseif ($page->externalId !== $firstPage->externalId) {
                throw new OrganizationParserException(
                    ParserErrorCode::InvalidSourceData,
                    'Страницы отзывов относятся к разным организациям.',
                );
            }

            $signature = $this->pageSignature($page);

            if ($signature !== null && isset($pageSignatures[$signature])) {
                throw new OrganizationParserException(
                    ParserErrorCode::InvalidSourceData,
                    'Яндекс Карты повторили уже загруженную страницу отзывов.',
                );
            }

            if ($signature !== null) {
                $pageSignatures[$signature] = true;
            }

            foreach ($page->reviews as $review) {
                $reviewsById[$review->externalId] = $review;
            }

            foreach ($page->sourceReviewIds as $reviewId) {
                $processedReviewIds[$reviewId] = true;
            }

            $processedReviews = count($processedReviewIds);
            $progress($processedReviews, $expectedReviews);

            if ($page->sourceReviewsCount === 0
                || $page->reviewsRemaining === 0
                || $page->currentPage >= $page->totalPages
                || $processedReviews >= $expectedReviews) {
                $sourceExhausted = true;

                break;
            }
        }

        if ($firstPage === null || $expectedReviews === null) {
            throw new OrganizationParserException(
                ParserErrorCode::EmptySourceResponse,
                'Яндекс Карты не вернули данные организации.',
            );
        }

        if (! $sourceExhausted && count($processedReviewIds) < $expectedReviews) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Парсер достиг лимита страниц до окончания выдачи Яндекс Карт.',
            );
        }

        return new ParsedOrganization(
            externalId: $firstPage->externalId,
            canonicalUrl: $firstPage->canonicalUrl,
            name: $firstPage->name,
            rating: $firstPage->rating,
            ratingsCount: $firstPage->ratingsCount,
            reviewsCount: $firstPage->reviewsCount,
            reviews: array_values($reviewsById),
            sourceReviewsCount: count($processedReviewIds),
            skippedReviewsCount: count($processedReviewIds) - count($reviewsById),
        );
    }

    private function pageSignature(YandexReviewsPage $page): ?string
    {
        if ($page->sourceReviewIds === []) {
            return null;
        }

        return hash('sha256', implode('|', $page->sourceReviewIds));
    }
}
