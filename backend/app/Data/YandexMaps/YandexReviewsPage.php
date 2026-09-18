<?php

namespace App\Data\YandexMaps;

use App\Data\ParsedReview;

final readonly class YandexReviewsPage
{
    /** @param list<ParsedReview> $reviews */
    public function __construct(
        public string $externalId,
        public string $canonicalUrl,
        public string $name,
        public ?float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
        public int $availableReviewsCount,
        public int $currentPage,
        public int $totalPages,
        public int $pageSize,
        public int $reviewsRemaining,
        public int $sourceReviewsCount,
        /** @var list<string> */
        public array $sourceReviewIds,
        public array $reviews,
    ) {}
}
