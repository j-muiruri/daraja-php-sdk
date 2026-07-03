<?php

declare(strict_types=1);

namespace Daraja\Auth\Cache;

use Daraja\Auth\AccessToken;

/**
 * Redis-backed OAuth token cache for multi-process environments.
 *
 * Usage (plain PHP with PhpRedis):
 *   $redis = new \Redis();
 *   $redis->connect('127.0.0.1', 6379);
 *   $cache = new RedisTokenCache($redis, keyPrefix: 'mpesa:token:prod');
 *
 * Usage (Laravel — bind in AppServiceProvider):
 *   $this->app->singleton(TokenCacheInterface::class, function () {
 *       return new RedisTokenCache(
 *           Redis::connection()->client(),
 *           keyPrefix: 'mpesa:token:' . config('daraja.shortcode'),
 *       );
 *   });
 *
 * The token serialisation is a simple JSON envelope — no external dependencies.
 */
final class RedisTokenCache implements TokenCacheInterface
{
    /** @param mixed $redis \Redis|\Predis\Client<string, mixed> */
    public function __construct(
        private readonly mixed  $redis,
        private readonly string $keyPrefix = 'daraja:token',
    ) {}

    public function get(): ?AccessToken
    {
        try {
            $raw = $this->redis->get($this->key());

            if ($raw === false || $raw === null || $raw === '') {
                return null;
            }

            /** @var array{token: string} $data */
            $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

            // Redis::ttl() returns int on success, or -1/-2 on no-expiry/missing key.
            // PHPStan sees the return as int|Redis depending on stub version;
            // is_int() narrows it safely without relying on the cast alone.
            $rawTtl = $this->redis->ttl($this->key());
            $ttl    = is_int($rawTtl) ? $rawTtl : 0;

            // Treat as expired if fewer than 10 seconds remain
            if ($ttl <= 10) {
                return null;
            }

            // Reconstruct with remaining TTL.
            // AccessToken subtracts 60s as a buffer internally, so we add it back
            // so the reconstructed token has the correct expiresAt value.
            return new AccessToken($data['token'], $ttl + 60);

        } catch (\Throwable) {
            // Cache failure is non-fatal — caller will re-fetch from Daraja
            return null;
        }
    }

    public function put(AccessToken $token): void
    {
        try {
            $ttl     = max(1, $token->expiresAt() - time());
            $payload = json_encode(['token' => $token->value()], JSON_THROW_ON_ERROR);

            $this->redis->setex($this->key(), $ttl, $payload);
        } catch (\Throwable) {
            // Cache failure is non-fatal
        }
    }

    public function forget(): void
    {
        try {
            $this->redis->del($this->key());
        } catch (\Throwable) {
            // ignore
        }
    }

    private function key(): string
    {
        return $this->keyPrefix;
    }
}