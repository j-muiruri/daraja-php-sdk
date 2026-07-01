<?php

declare(strict_types=1);

namespace Daraja;

use Daraja\Enums\Environment;
use Daraja\Exceptions\ValidationException;

/**
 * Immutable configuration object for the Daraja SDK.
 *
 * Usage:
 *   $config = Config::make(
 *       consumerKey:    'YOUR_CONSUMER_KEY',
 *       consumerSecret: 'YOUR_CONSUMER_SECRET',
 *       shortcode:      '174379',
 *       passkey:        'YOUR_PASSKEY',
 *       environment:    Environment::Sandbox,
 *   );
 */
final class Config
{
    public function __construct(
        public readonly string      $consumerKey,
        public readonly string      $consumerSecret,
        public readonly string      $shortcode,
        public readonly string      $passkey,
        public readonly Environment $environment,
        /** Security credential: Base64-encoded encrypted initiator password. Required for B2C, B2B, Reversal, Balance. */
        public readonly string      $securityCredential = '',
        /** Initiator name for operator APIs (B2C, B2B, Reversal, Balance). */
        public readonly string      $initiatorName = '',
        /** Default callback/result/timeout URLs — can be overridden per request. */
        public readonly string      $callbackUrl = '',
        public readonly string      $resultUrl = '',
        public readonly string      $timeoutUrl = '',
        /** HTTP client timeout in seconds. */
        public readonly int         $timeout = 30,
    ) {
        $this->validate();
    }

    public static function make(
        string      $consumerKey,
        string      $consumerSecret,
        string      $shortcode,
        string      $passkey,
        Environment $environment = Environment::Sandbox,
        string      $securityCredential = '',
        string      $initiatorName = '',
        string      $callbackUrl = '',
        string      $resultUrl = '',
        string      $timeoutUrl = '',
        int         $timeout = 30,
    ): self {
        return new self(
            consumerKey:        $consumerKey,
            consumerSecret:     $consumerSecret,
            shortcode:          $shortcode,
            passkey:            $passkey,
            environment:        $environment,
            securityCredential: $securityCredential,
            initiatorName:      $initiatorName,
            callbackUrl:        $callbackUrl,
            resultUrl:          $resultUrl,
            timeoutUrl:         $timeoutUrl,
            timeout:            $timeout,
        );
    }

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }

    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    private function validate(): void
    {
        $errors = [];

        if (empty($this->consumerKey)) {
            $errors['consumer_key'] = 'Consumer key is required';
        }

        if (empty($this->consumerSecret)) {
            $errors['consumer_secret'] = 'Consumer secret is required';
        }

        if (empty($this->shortcode)) {
            $errors['shortcode'] = 'Business shortcode is required';
        }

        if (empty($this->passkey)) {
            $errors['passkey'] = 'Passkey is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Invalid Daraja configuration');
        }
    }
}
