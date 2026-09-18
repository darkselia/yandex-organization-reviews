<?php

namespace Tests\Unit\YandexMaps;

use App\Enums\ParserErrorCode;
use App\Exceptions\OrganizationParserException;
use App\Services\YandexMaps\YandexMapsClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YandexMapsClientTest extends TestCase
{
    private const ORGANIZATION_URL = 'https://yandex.ru/maps/org/testovaya_kofeynya/123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_fetches_requested_reviews_page(): void
    {
        Http::fake([
            '*' => Http::response('<html>response</html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        $body = (new YandexMapsClient)->fetchReviewsPage(self::ORGANIZATION_URL, 2);

        $this->assertSame('<html>response</html>', $body);
        Http::assertSent(fn ($request): bool => $request->url()
            === self::ORGANIZATION_URL.'/reviews/?page=2');
    }

    #[DataProvider('responseErrors')]
    public function test_it_classifies_http_and_response_errors(
        int $status,
        string $contentType,
        string $body,
        ParserErrorCode $expectedCode,
    ): void {
        Http::fake([
            '*' => Http::response($body, $status, ['Content-Type' => $contentType]),
        ]);

        try {
            (new YandexMapsClient)->fetchReviewsPage(self::ORGANIZATION_URL, 1);
            $this->fail("HTTP {$status} did not produce a parser exception.");
        } catch (OrganizationParserException $exception) {
            $this->assertSame($expectedCode, $exception->errorCode);
        }
    }

    /** @return list<array{int, string, string, ParserErrorCode}> */
    public static function responseErrors(): array
    {
        return [
            [403, 'text/html', 'blocked', ParserErrorCode::SourceBlocked],
            [429, 'text/html', 'limited', ParserErrorCode::SourceRateLimited],
            [503, 'text/html', 'unavailable', ParserErrorCode::SourceUnavailable],
            [200, 'application/json', '{}', ParserErrorCode::InvalidSourceData],
            [200, 'text/html', '', ParserErrorCode::EmptySourceResponse],
            [
                200,
                'text/html',
                '<html><head><title>Ой!</title></head><body class="CheckboxCaptcha"></body></html>',
                ParserErrorCode::SourceBlocked,
            ],
        ];
    }

    public function test_it_rejects_non_yandex_url_before_http_request(): void
    {
        Http::fake();

        try {
            (new YandexMapsClient)->fetchReviewsPage('https://example.com/maps/org/test/1', 1);
            $this->fail('Unsupported URL did not produce a parser exception.');
        } catch (OrganizationParserException $exception) {
            $this->assertSame(ParserErrorCode::InvalidSourceData, $exception->errorCode);
        }

        Http::assertNothingSent();
    }
}
