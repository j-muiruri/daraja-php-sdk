<?php

declare(strict_types=1);

namespace Daraja\Auth;

use Daraja\Auth\Cache\InMemoryTokenCache;
use Daraja\Auth\Cache\TokenCacheInterface;
use Daraja\Config;
use Daraja\Exceptions\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Manages OAuth 2.0 access tokens for the Daraja API.
 *
 * Tokens are cached via a pluggable TokenCacheInterface.
 * Default: InMemoryTokenCache (per-process).
 * Production multi-pod: inject RedisTokenCache.
 */
class AccessTokenManager
{
    private readonly TokenCacheInterface $cache;

    public function __construct(
        private readonly Config $config,
        private readonly Client $httpClient,
        ?TokenCacheInterface    $cache = null,
    ) {
        $this->cache = $cache ?? new InMemoryTokenCache();
    }

    /**
     * Returns a valid access token, fetching a new one if necessary.
     *
     * @throws AuthenticationException
     */
    public function get(): AccessToken
    {
        $cached = $this->cache->get();

        if ($cached !== null) {
            return $cached;
        }

        $token = $this->fetch();
        $this->cache->put($token);

        return $token;
    }

    /**
     * Force-fetch a fresh token, bypassing cache.
     *
     * @throws AuthenticationException
     */
    public function refresh(): AccessToken
    {
        $this->cache->forget();

        return $this->get();
    }

    /**
     * @throws AuthenticationException
     */
    private function fetch(): AccessToken
    {
        $credentials = base64_encode(
            $this->config->consumerKey . ':' . $this->config->consumerSecret
        );

        try {
            $response = $this->httpClient->get(
                $this->config->baseUrl() . '/oauth/v1/generate',
                [
                    'query'   => ['grant_type' => 'client_credentials'],
                    'headers' => [
                        'Authorization' => 'Basic ' . $credentials,
                        'Accept'        => 'application/json',
                    ],
                    'timeout' => $this->config->timeout,
                ]
            );

            /** @var array{access_token: string, expires_in: string|int} $body */
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (empty($body['access_token'])) {
                throw new AuthenticationException('Access token missing from Daraja response');
            }

            return new AccessToken(
                token: $body['access_token'],
                expiresIn: (int) ($body['expires_in']),
            );
        } catch (GuzzleException $e) {
            throw new AuthenticationException(
                'Failed to obtain Daraja access token: ' . $e->getMessage(),
                previous: $e,
            );
        } catch (\JsonException $e) {
            throw new AuthenticationException(
                'Invalid JSON in Daraja auth response: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
