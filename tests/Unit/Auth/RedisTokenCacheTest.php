<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Auth;

use Daraja\Auth\AccessToken;
use Daraja\Auth\Cache\InMemoryTokenCache;
use Daraja\Auth\Cache\RedisTokenCache;
use PHPUnit\Framework\TestCase;

final class RedisTokenCacheTest extends TestCase
{
    private function makeRedisMock(
        string $storedValue = '',
        int    $ttl = 3600,
    ): object {
        return new class ($storedValue, $ttl) {
            public array $stored = [];
            public int   $deleted = 0;

            public function __construct(
                private readonly string $value,
                private readonly int    $ttl,
            ) {}

            public function get(string $key): string|false
            {
                return $this->value !== '' ? $this->value : false;
            }

            public function setex(string $key, int $ttl, string $value): void
            {
                $this->stored[$key] = ['value' => $value, 'ttl' => $ttl];
            }

            public function ttl(string $key): int
            {
                return $this->ttl;
            }

            public function del(string $key): void
            {
                $this->deleted++;
            }
        };
    }

    public function test_returns_null_when_redis_has_no_value(): void
    {
        $redis = $this->makeRedisMock('');
        $cache = new RedisTokenCache($redis);

        self::assertNull($cache->get());
    }

    public function test_returns_token_when_redis_has_valid_value(): void
    {
        $stored = json_encode(['token' => 'redis_token'], JSON_THROW_ON_ERROR);
        $redis  = $this->makeRedisMock($stored, 3600);
        $cache  = new RedisTokenCache($redis);

        $token = $cache->get();

        self::assertNotNull($token);
        self::assertSame('redis_token', $token->value());
    }

    public function test_returns_null_when_ttl_is_low(): void
    {
        $stored = json_encode(['token' => 'expiring'], JSON_THROW_ON_ERROR);
        $redis  = $this->makeRedisMock($stored, 5); // TTL <= 10 = treat as expired
        $cache  = new RedisTokenCache($redis);

        self::assertNull($cache->get());
    }

    public function test_stores_token_in_redis(): void
    {
        $redis = $this->makeRedisMock();
        $cache = new RedisTokenCache($redis, 'test:key');
        $token = new AccessToken('store_me', 3600);

        $cache->put($token);

        self::assertArrayHasKey('test:key', $redis->stored);
        $decoded = json_decode($redis->stored['test:key']['value'], true);
        self::assertSame('store_me', $decoded['token']);
    }

    public function test_forget_calls_del_on_redis(): void
    {
        $redis = $this->makeRedisMock();
        $cache = new RedisTokenCache($redis);

        $cache->forget();

        self::assertSame(1, $redis->deleted);
    }
}
