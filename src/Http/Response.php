<?php

declare(strict_types=1);

namespace Daraja\Http;

/**
 * Wraps a raw Daraja API response for convenient access.
 *
 * @template TData of array<string, mixed>
 */
final class Response
{
    /**
     * @param TData $data
     */
    public function __construct(
        private readonly array $data,
        private readonly int   $statusCode,
    ) {}

    /**
     * @param  array<string, mixed> $data
     * @return self<array<string, mixed>>
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

    /**
     * @return TData
     */
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
     * M-Pesa API 0 = success, anything else = failure (even with HTTP 200).
     */
    public function isAccepted(): bool
    {
        $responseCode = $this->getString('ResponseCode');

        return $responseCode === '0' || $responseCode === '';
    }

    /**
     * Conversation/Originator IDs returned after accepted async requests.
     */
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

    /** STK Push-specific: CheckoutRequestID */
    public function checkoutRequestId(): string
    {
        return $this->getString('CheckoutRequestID');
    }

    /** STK Query-specific: ResultDesc */
    public function resultDescription(): string
    {
        return $this->getString('ResultDesc');
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}