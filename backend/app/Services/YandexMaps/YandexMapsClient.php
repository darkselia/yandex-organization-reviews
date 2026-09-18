<?php

namespace App\Services\YandexMaps;

use App\Enums\ParserErrorCode;
use App\Exceptions\OrganizationParserException;
use App\Rules\YandexOrganizationUrl;
use Composer\CaBundle\CaBundle;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class YandexMapsClient
{
    private CookieJar $cookies;

    private ?float $lastRequestAt = null;

    public function __construct(
        private readonly ?int $requestDelayMilliseconds = null,
    ) {
        $this->cookies = new CookieJar;
    }

    public function fetchReviewsPage(string $organizationUrl, int $page): string
    {
        if (! YandexOrganizationUrl::isSupported($organizationUrl)
            || ! str_contains((string) parse_url($organizationUrl, PHP_URL_PATH), '/maps/org/')) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Парсер получил неподдерживаемую ссылку организации.',
            );
        }

        $this->waitForRequestSlot();

        try {
            $response = Http::withOptions([
                'cookies' => $this->cookies,
                'verify' => CaBundle::getSystemCaRootBundlePath(),
            ])
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])
                ->withUserAgent(
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    .'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
                )
                ->connectTimeout((int) config('services.yandex_maps.connect_timeout_seconds', 10))
                ->timeout((int) config('services.yandex_maps.timeout_seconds', 20))
                ->retry(
                    [1000, 3000],
                    when: static fn (\Exception $exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->get($this->reviewsUrl($organizationUrl), ['page' => $page]);
            $this->lastRequestAt = microtime(true);
        } catch (ConnectionException $exception) {
            $this->lastRequestAt = microtime(true);

            throw new OrganizationParserException(
                ParserErrorCode::SourceUnavailable,
                'Не удалось подключиться к Яндекс Картам.',
                $exception,
            );
        }

        $this->ensureSuccessfulResponse($response);

        return $response->body();
    }

    private function reviewsUrl(string $organizationUrl): string
    {
        return rtrim($organizationUrl, '/').'/reviews/';
    }

    private function ensureSuccessfulResponse(Response $response): void
    {
        if ($response->status() === 403) {
            throw new OrganizationParserException(
                ParserErrorCode::SourceBlocked,
                'Яндекс Карты заблокировали запрос.',
            );
        }

        if ($response->status() === 429) {
            throw new OrganizationParserException(
                ParserErrorCode::SourceRateLimited,
                'Яндекс Карты временно ограничили частоту запросов.',
            );
        }

        if (! $response->successful()) {
            throw new OrganizationParserException(
                ParserErrorCode::SourceUnavailable,
                "Яндекс Карты вернули HTTP {$response->status()}.",
            );
        }

        $contentType = strtolower($response->header('Content-Type'));

        if (! str_contains($contentType, 'text/html')
            && ! str_contains($contentType, 'application/xhtml+xml')) {
            throw new OrganizationParserException(
                ParserErrorCode::InvalidSourceData,
                'Яндекс Карты вернули ответ неожиданного типа.',
            );
        }

        if (trim($response->body()) === '') {
            throw new OrganizationParserException(
                ParserErrorCode::EmptySourceResponse,
                'Яндекс Карты вернули пустой ответ.',
            );
        }

        if ($this->isCaptchaPage($response->body())) {
            throw new OrganizationParserException(
                ParserErrorCode::SourceBlocked,
                'Яндекс Карты запросили проверку пользователя.',
            );
        }
    }

    private function waitForRequestSlot(): void
    {
        $delayMilliseconds = $this->requestDelayMilliseconds
            ?? (int) config('services.yandex_maps.request_delay_ms', 500);

        if ($delayMilliseconds <= 0 || $this->lastRequestAt === null) {
            return;
        }

        $elapsedMicroseconds = (int) ((microtime(true) - $this->lastRequestAt) * 1_000_000);
        $remainingMicroseconds = ($delayMilliseconds * 1000) - $elapsedMicroseconds;

        if ($remainingMicroseconds > 0) {
            usleep($remainingMicroseconds);
        }
    }

    private function isCaptchaPage(string $body): bool
    {
        return preg_match('~<title[^>]*>\s*(?:Ой!?|Captcha)\s*</title>~iu', $body) === 1
            || preg_match('~class=["\'][^"\']*(?:CheckboxCaptcha|SmartCaptcha)[^"\']*["\']~iu', $body) === 1;
    }
}
