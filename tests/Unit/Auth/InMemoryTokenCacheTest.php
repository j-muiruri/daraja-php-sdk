<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Auth;

use Daraja\Auth\AccessToken;
use Daraja\Auth\Cache\InMemoryTokenCache;
use Daraja\Auth\Cache\RedisTokenCache;
use PHPUnit\Framework\TestCase;

final class InMemoryTokenCacheTest extends TestCase
{
    public function test_returns_null_when_empty(): void
    {
        $cache = new InMemoryTokenCache();

        self::assertNull($cache->get());
    }

    public function test_stores_and_retrieves_valid_token(): void
    {
        $cache = new InMemoryTokenCache();
        $token = new AccessToken('abc123', 3600);

        $cache->put($token);

        self::assertSame('abc123', $cache->get()?->value());
    }

    public function test_returns_null_for_expired_token(): void
    {
        $cache = new InMemoryTokenCache();
        $token = new AccessToken('abc123', 0); // expires immediately with 60s buffer

        $cache->put($token);

        self::assertNull($cache->get());
    }

    public function test_forget_clears_cached_token(): void
    {
        $cache = new InMemoryTokenCache();
        $cache->put(new AccessToken('abc123', 3600));
        $cache->forget();

        self::assertNull($cache->get());
    }
}
