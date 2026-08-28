<?php

declare(strict_types=1);

namespace Daraja\Support;

final class Url
{
    public static function isHttps(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }
}
