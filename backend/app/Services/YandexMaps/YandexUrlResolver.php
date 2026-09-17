<?php

namespace App\Services\YandexMaps;

use App\Data\NormalizedYandexUrl;
use App\Rules\YandexOrganizationUrl;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class YandexUrlResolver
{
    private const MAX_REDIRECTS = 3;

    private const TIMEOUT_SECONDS = 5;

    public function __construct(private readonly YandexUrlNormalizer $normalizer) {}

    public function resolve(string $url): NormalizedYandexUrl
    {
        $normalized = $this->normalizer->normalize($url);

        if (! $normalized->short) {
            return $this->requireOrganizationId($normalized);
        }

        $currentUrl = $normalized->url;
        $deadline = microtime(true) + self::TIMEOUT_SECONDS;

        try {
            for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
                $remainingSeconds = $deadline - microtime(true);

                if ($remainingSeconds <= 0) {
                    throw new YandexUrlException('Яндекс Карты не ответили вовремя. Попробуйте ещё раз.');
                }

                $response = Http::accept('text/html')
                    ->withUserAgent('YandexReviews/1.0')
                    ->connectTimeout(min(2, $remainingSeconds))
                    ->timeout($remainingSeconds)
                    ->withoutRedirecting()
                    ->get($currentUrl);

                if ($response->redirect()) {
                    if ($redirects === self::MAX_REDIRECTS) {
                        throw new YandexUrlException('Короткая ссылка содержит слишком много перенаправлений.');
                    }

                    $location = $response->header('Location');

                    if ($location === '') {
                        throw new YandexUrlException('Яндекс вернул перенаправление без адреса.');
                    }

                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));

                    if (! YandexOrganizationUrl::hasAllowedHost($currentUrl)) {
                        throw new YandexUrlException('Короткая ссылка ведёт за пределы Яндекс Карт.');
                    }

                    if (YandexOrganizationUrl::isSupported($currentUrl)) {
                        $resolved = $this->normalizer->normalize($currentUrl);

                        if (! $resolved->short) {
                            return $this->requireOrganizationId($resolved);
                        }
                    }

                    continue;
                }

                if (! $response->successful()) {
                    throw new YandexUrlException('Не удалось открыть короткую ссылку Яндекс Карт.');
                }

                break;
            }
        } catch (ConnectionException) {
            throw new YandexUrlException('Не удалось подключиться к Яндекс Картам. Попробуйте ещё раз.');
        }

        throw new YandexUrlException('Не удалось определить организацию по короткой ссылке.');
    }

    private function requireOrganizationId(NormalizedYandexUrl $url): NormalizedYandexUrl
    {
        if ($url->externalId === null) {
            throw new YandexUrlException('В ссылке отсутствует идентификатор организации.');
        }

        return $url;
    }
}
