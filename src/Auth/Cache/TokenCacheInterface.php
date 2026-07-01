<?php

declare(strict_types=1);

namespace Daraja\Auth\Cache;

use Daraja\Auth\AccessToken;

/**
 * Contract for pluggable OAuth token caching.
 *
 * The SDK ships with InMemoryTokenCache (default — single process, no deps).
 * For multi-process environments (PHP-FPM, queue workers, multiple pods),
 * implement this interface backed by Redis or a shared store to avoid
 * hammering the Daraja OAuth endpoint with redundant token fetches.
 *
 * Example Redis implementation — see RedisTokenCache in this namespace.
 */
interface TokenCacheInterface
{
    /**
     * Retrieve a cached token, or null if absent or expired.
     */
    public function get(): ?AccessToken;

    /**
     * Store a token in the cache.
     */
    public function put(AccessToken $token): void;

    /**
     * Remove the cached token (force-refresh on next get()).
     */
    public function forget(): void;
}
