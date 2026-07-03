<?php

declare(strict_types=1);

namespace Daraja\Http;

/**
 * Wraps a raw Daraja API response for convenient access.
 *
 * Every Daraja API returns a flat JSON object; the data is always
 * array<string, mixed>, so no generic type parameter is needed here.
 */
final class Response
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly array $data,
        private readonly int   $statusCode,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, int $statusCode = 200): self
    {
        return new self($data, $statusCode);
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) ($this->data[$key] ?? $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->data[$key] ?? $default);
    }

    /**
     * M-Pesa API: ResultCode/ResponseCode "0" = success.
     * Empty string also counts as accepted (some endpoints omit the field).
     */
    public function isAccepted(): bool
    {
        $responseCode = $this->getString('ResponseCode');

        return $responseCode === '0' || $responseCode === '';
    }

    public function conversationId(): string
    {
        return $this->getString('ConversationID');
    }

    public function originatorConversationId(): string
    {
        return $this->getString('OriginatorConversationID');
    }

    public function responseDescription(): string
    {
        return $this->getString('ResponseDescription');
    }

    /** STK Push: CheckoutRequestID from the push response. */
    public function checkoutRequestId(): string
    {
        return $this->getString('CheckoutRequestID');
    }

    /** STK Query: human-readable result description. */
    public function resultDescription(): string
    {
        return $this->getString('ResultDesc');
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}