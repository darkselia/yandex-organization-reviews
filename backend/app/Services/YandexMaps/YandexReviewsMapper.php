<?php

namespace App\Services\YandexMaps;

use App\Data\ParsedReview;
use App\Data\YandexMaps\YandexReviewsPage;
use App\Enums\ParserErrorCode;
use App\Exceptions\OrganizationParserException;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Exception;
use JsonException;

class YandexReviewsMapper
{
    public function map(string $html, string $sourceUrl, int $requestedPage): ?YandexReviewsPage
    {
        $state = $this->extractState($html);
        $item = $state['stack'][0]['results']['items'][0] ?? null;

        if ($item === null && $requestedPage > 1) {
            return null;
        }

        if (! is_array($item)) {
            throw $this->schemaChanged('В JSON отсутствует карточка организации.');
        }

        $externalId = $this->requiredString($item, 'id');
        $name = $this->requiredString($item, 'title');
        $seoname = $this->requiredString($item, 'seoname');
        $ratingData = $this->requiredArray($item, 'ratingData');
        $reviewResults = $this->requiredArray($item, 'reviewResults');
        $reviewItems = $this->requiredArray($reviewResults, 'reviews');
        $params = $this->requiredArray($reviewResults, 'params');
        $currentPage = $this->requiredInteger($params, 'page');

        if ($currentPage !== $requestedPage) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Яндекс Карты вернули другую страницу отзывов.',
            );
        }

        $reviews = [];
        $sourceReviewIds = [];

        foreach ($reviewItems as $review) {
            if (! is_array($review)) {
                throw new OrganizationParserException(
                    ParserErrorCode::InvalidSourceData,
                    'Яндекс Карты вернули отзыв неожиданного формата.',
                );
            }

            $reviewId = $this->requiredString($review, 'reviewId');
            $sourceReviewIds[] = $reviewId;
            $mappedReview = $this->mapReview($review, $externalId, $reviewId);

            if ($mappedReview !== null) {
                $reviews[] = $mappedReview;
            }
        }

        return new YandexReviewsPage(
            externalId: $externalId,
            canonicalUrl: $this->canonicalUrl($sourceUrl, $seoname, $externalId),
            name: $name,
            rating: $this->nullableRating($ratingData),
            ratingsCount: $this->nonNegativeInteger($ratingData, 'ratingCount'),
            reviewsCount: $this->nonNegativeInteger($ratingData, 'reviewCount'),
            availableReviewsCount: $this->nonNegativeInteger($params, 'count'),
            currentPage: $currentPage,
            totalPages: $this->nonNegativeInteger($params, 'totalPages'),
            pageSize: $this->positiveInteger($params, 'limit'),
            reviewsRemaining: $this->nonNegativeInteger($params, 'reviewsRemained'),
            sourceReviewsCount: count($reviewItems),
            sourceReviewIds: $sourceReviewIds,
            reviews: $reviews,
        );
    }

    /** @return array<string, mixed> */
    private function extractState(string $html): array
    {
        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Не удалось разобрать HTML Яндекс Карт.',
            );
        }

        $nodes = (new DOMXPath($document))->query(
            '//script[contains(concat(" ", normalize-space(@class), " "), " state-view ")]',
        );
        $json = $nodes?->item(0)?->textContent;

        if (! is_string($json) || trim($json) === '') {
            throw $this->schemaChanged('В странице отсутствует JSON состояния Яндекс Карт.');
        }

        try {
            $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'JSON состояния Яндекс Карт повреждён.',
                $exception,
            );
        }

        if (! is_array($state)) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'JSON состояния Яндекс Карт имеет неожиданный формат.',
            );
        }

        return $state;
    }

    /** @param array<string, mixed> $review */
    private function mapReview(array $review, string $organizationId, string $reviewId): ?ParsedReview
    {
        $rating = $this->requiredInteger($review, 'rating');

        if ($rating === 0) {
            return null;
        }

        if ($rating < 0 || $rating > 5) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Отзыв {$reviewId} содержит оценку {$rating} вне диапазона от 1 до 5.",
            );
        }

        $authorName = 'Пользователь Яндекса';
        $author = $review['author'] ?? null;

        if (is_array($author) && isset($author['name']) && is_string($author['name'])
            && trim($author['name']) !== '') {
            $authorName = $author['name'];
        } elseif ($author !== null && ! is_array($author)) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Отзыв {$reviewId} содержит автора неожиданного формата.",
            );
        }

        $updatedTime = $this->requiredString($review, 'updatedTime');

        if (isset($review['businessId']) && (string) $review['businessId'] !== $organizationId) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Отзыв {$reviewId} относится к другой организации.",
            );
        }

        $text = $review['text'] ?? null;

        if ($text !== null && ! is_string($text)) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Отзыв {$reviewId} содержит текст неожиданного формата.",
            );
        }

        try {
            $publishedAt = new DateTimeImmutable($updatedTime);
        } catch (Exception $exception) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Отзыв {$reviewId} содержит некорректную дату.",
                $exception,
            );
        }

        return new ParsedReview(
            externalId: $reviewId,
            authorName: $authorName,
            publishedAt: $publishedAt,
            text: $text,
            rating: $rating,
        );
    }

    /** @param array<string, mixed> $ratingData */
    private function nullableRating(array $ratingData): ?float
    {
        if (! array_key_exists('ratingValue', $ratingData)) {
            throw $this->schemaChanged('В JSON отсутствует поле ratingValue.');
        }

        $rating = $ratingData['ratingValue'];

        if ($rating === null) {
            return null;
        }

        if (! is_int($rating) && ! is_float($rating)) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Рейтинг организации имеет неожиданный формат.',
            );
        }

        $rating = (float) $rating;

        if ($rating < 0 || $rating > 5) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Рейтинг организации находится вне диапазона от 0 до 5.',
            );
        }

        return round($rating, 1);
    }

    private function canonicalUrl(string $sourceUrl, string $seoname, string $externalId): string
    {
        $host = parse_url($sourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Не удалось определить домен карточки организации.',
            );
        }

        return 'https://'.strtolower($host).'/maps/org/'.rawurlencode($seoname).'/'.$externalId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    private function requiredArray(array $data, string $key): array
    {
        if (! array_key_exists($key, $data)) {
            throw $this->schemaChanged("В JSON отсутствует поле {$key}.");
        }

        if (! is_array($data[$key])) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Поле {$key} имеет неожиданный формат.",
            );
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        if (! array_key_exists($key, $data)) {
            throw $this->schemaChanged("В JSON отсутствует поле {$key}.");
        }

        if (! is_string($data[$key]) || trim($data[$key]) === '') {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Поле {$key} должно быть непустой строкой.",
            );
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function requiredInteger(array $data, string $key): int
    {
        if (! array_key_exists($key, $data)) {
            throw $this->schemaChanged("В JSON отсутствует поле {$key}.");
        }

        if (! is_int($data[$key])) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Поле {$key} должно быть целым числом.",
            );
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function nonNegativeInteger(array $data, string $key): int
    {
        $value = $this->requiredInteger($data, $key);

        if ($value < 0) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Поле {$key} не может быть отрицательным.",
            );
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function positiveInteger(array $data, string $key): int
    {
        $value = $this->requiredInteger($data, $key);

        if ($value < 1) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                "Поле {$key} должно быть положительным.",
            );
        }

        return $value;
    }

    private function schemaChanged(string $message): OrganizationParserException
    {
        return new OrganizationParserException(ParserErrorCode::SourceSchemaChanged, $message);
    }
}
