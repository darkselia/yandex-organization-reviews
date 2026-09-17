<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YandexOrganizationUrl implements ValidationRule
{
    private const ALLOWED_HOSTS = [
        'yandex.ru',
        'www.yandex.ru',
        'yandex.com',
        'www.yandex.com',
        'ya.ru',
        'www.ya.ru',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSupported($value)) {
            $fail('Укажите ссылку на карточку организации в Яндекс Картах.');
        }
    }

    public static function isSupported(string $url): bool
    {
        $parts = parse_url(trim($url));

        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true)
            || ! in_array($host, self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return false;
        }

        return preg_match('~^/maps/(?:org(?:/|$)|-/[^/]+/?$)~u', $path) === 1;
    }

    public static function hasAllowedHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), self::ALLOWED_HOSTS, true);
    }
}
