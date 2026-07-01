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
    /**
     *@param \Illuminate\Support\Facades\Redis|\Predis\Client<string, mixed> $redis 
     */
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

            /** @var array{token: string, expires_in: int} $data */
            $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

            // Reconstruct with remaining TTL (Redis handles expiry; we add a 10s buffer)
            $ttl = max(0, (int) $this->redis->ttl($this->key()));

            return $ttl > 10 ? new AccessToken($data['token'], $ttl + 60) : null; // +60 because token subtracts 60 internally
        } catch (\Throwable) {
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
            // Cache failure is non-fatal — next request will re-fetch
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
