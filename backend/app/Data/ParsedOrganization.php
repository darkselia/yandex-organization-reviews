<?php

namespace App\Data;

final readonly class ParsedOrganization
{
    /** @param list<ParsedReview> $reviews */
    public function __construct(
        public string $externalId,
        public string $canonicalUrl,
        public string $name,
        public ?float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
        public array $reviews,
    ) {}
}
