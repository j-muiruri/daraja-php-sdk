<?php

declare(strict_types=1);

namespace Daraja\Http;

use Daraja\Auth\AccessTokenManager;
use Daraja\Config;
use Daraja\Exceptions\ApiException;
use Daraja\Exceptions\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

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
     */
    public function post(string $endpoint, array $payload): Response
    {
        return $this->send('POST', $endpoint, $payload);
    }

    /**
     * @param  array<string, string> $query
     * @throws ApiException
     * @throws AuthenticationException
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
            $url         = $this->config->baseUrl() . $endpoint;
            $rawResponse = $this->guzzle->request($method, $url, $options);

            /** @var array<string, mixed> $body */
            $body = json_decode(
                (string) $rawResponse->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return Response::fromArray($body, $rawResponse->getStatusCode());
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response === null) {
                throw new ApiException(
                    statusCode: 0,
                    errorCode: 'NETWORK_ERROR',
                    errorMessage: 'Network error: ' . $e->getMessage(),
                );
            }

            $statusCode = $response->getStatusCode();

            // Retry once on 401 — token may have expired between cache read and request
            if ($statusCode === 401 && $retry) {
                $this->tokenManager->refresh();

                return $this->send($method, $endpoint, $payload, $query, false);
            }

            $errorBody = $this->decodeErrorBody((string) $response->getBody());

            throw new ApiException(
                statusCode: $statusCode,
                errorCode: $errorBody['errorCode']    ?? (string) $statusCode,
                errorMessage: $errorBody['errorMessage'] ?? $e->getMessage(),
            );
        } catch (GuzzleException $e) {
            throw new ApiException(
                statusCode: 0,
                errorCode: 'NETWORK_ERROR',
                errorMessage: 'Network error: ' . $e->getMessage(),
            );
        } catch (\JsonException $e) {
            throw new ApiException(
                statusCode: 0,
                errorCode: 'PARSE_ERROR',
                errorMessage: 'Failed to parse Daraja response: ' . $e->getMessage(),
            );
        }
    }

    /** @return array<string, string> */
    private function decodeErrorBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return array_map(
                static fn(mixed $value): string => is_scalar($value)
                    ? (string) $value
                    : json_encode($value, JSON_THROW_ON_ERROR),
                $decoded
            );
        } catch (\JsonException) {
            return [];
        }
    }
}
