<?php

declare(strict_types=1);

namespace Daraja\Http;

use Daraja\Auth\AccessTokenManager;
use Daraja\Config;
use Daraja\Exceptions\ApiException;
use Daraja\Exceptions\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Central HTTP client for all Daraja API calls.
 *
 * Responsibilities:
 * - Automatically attaches a valid Bearer token to every request
 * - Retries once on 401 (token may have expired mid-request)
 * - Maps HTTP/API errors to typed exceptions
 * - Returns typed Response objects
 */
final class HttpClient
{
    public function __construct(
        private readonly Config             $config,
        private readonly AccessTokenManager $tokenManager,
        private readonly Client             $guzzle,
    ) {}

    /**
     * @param  array<string, mixed> $payload
     * @throws ApiException
     * @throws AuthenticationException
     * @return Response
     */
    public function post(string $endpoint, array $payload): Response
    {
        return $this->send('POST', $endpoint, $payload);
    }

    /**
     * @param  array<string, string> $query
     * @throws ApiException
     * @throws AuthenticationException
     * @return Response
     */
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->send('GET', $endpoint, [], $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string> $query
     * @throws ApiException
     * @throws AuthenticationException
     * @return Response
     */
    private function send(
        string $method,
        string $endpoint,
        array  $payload = [],
        array  $query = [],
        bool   $retry = true,
    ): Response {
        $token = $this->tokenManager->get();

        $options = [
            'headers' => [
                'Authorization' => $token->bearerHeader(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => $this->config->timeout,
        ];

        if ($payload !== []) {
            $options['json'] = $payload;
        }

        if ($query !== []) {
            $options['query'] = $query;
        }

        try {
            $url = $this->config->baseUrl() . $endpoint;
            $rawResponse = $this->guzzle->request($method, $url, $options);

            /** @var array<string, mixed> $body */
            $body = json_decode(
                (string) $rawResponse->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return Response::fromArray($body, $rawResponse->getStatusCode());
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            // Retry once on 401 — token may have just expired
            if ($statusCode === 401 && $retry) {
                $this->tokenManager->refresh();

                return $this->send($method, $endpoint, $payload, $query, false);
            }

            /** @var array<string, string> $errorBody */
            $errorBody = json_decode(
                (string) $e->getResponse()->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            throw new ApiException(
                statusCode:   $statusCode,
                errorCode:    $errorBody['errorCode'] ?? (string) $statusCode,
                errorMessage: $errorBody['errorMessage'] ?? $e->getMessage(),
            );
        } catch (GuzzleException $e) {
            throw new ApiException(
                statusCode:   0,
                errorCode:    'NETWORK_ERROR',
                errorMessage: 'Network error: ' . $e->getMessage(),
            );
        } catch (\JsonException $e) {
            throw new ApiException(
                statusCode:   0,
                errorCode:    'PARSE_ERROR',
                errorMessage: 'Failed to parse Daraja response: ' . $e->getMessage(),
            );
        }
    }
}
