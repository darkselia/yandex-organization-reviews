<?php

namespace App\Data;

final readonly class NormalizedYandexUrl
{
    public function __construct(
        public string $url,
        public ?string $externalId,
        public bool $short,
    ) {}
}
