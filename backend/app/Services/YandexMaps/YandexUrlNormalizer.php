<?php

namespace App\Services\YandexMaps;

use App\Data\NormalizedYandexUrl;
use App\Rules\YandexOrganizationUrl;

class YandexUrlNormalizer
{
    public function normalize(string $url): NormalizedYandexUrl
    {
        $url = trim($url);

        if (! YandexOrganizationUrl::isSupported($url)) {
            throw new YandexUrlException(
                'Укажите ссылку на карточку организации в Яндекс Картах.',
            );
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = '/'.ltrim($parts['path'], '/');
        $path = $path === '/' ? $path : rtrim($path, '/');
        $externalId = $this->extractExternalId($path);

        return new NormalizedYandexUrl(
            url: "https://{$host}{$path}",
            externalId: $externalId,
            short: preg_match('~^/maps/-/[^/]+$~u', $path) === 1,
        );
    }

    private function extractExternalId(string $path): ?string
    {
        if (preg_match('~^/maps/org/(?:[^/]+/)?(\d+)$~u', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
