<?php

declare(strict_types=1);

namespace Daraja\Auth\Cache;

use Daraja\Auth\AccessToken;

/**
 * Default in-memory token cache.
 *
 * Tokens live for the lifetime of the PHP process (single request or long-running CLI).
 * Use RedisTokenCache (or implement TokenCacheInterface) for multi-process environments.
 */
final class InMemoryTokenCache implements TokenCacheInterface
{
    private ?AccessToken $token = null;

    public function get(): ?AccessToken
    {
        if ($this->token === null || $this->token->isExpired()) {
            return null;
        }

        return $this->token;
    }

    public function put(AccessToken $token): void
    {
        $this->token = $token;
    }

    public function forget(): void
    {
        $this->token = null;
    }
}
