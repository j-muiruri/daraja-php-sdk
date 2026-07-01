<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit;

use Daraja\Config;
use Daraja\Enums\Environment;
use Daraja\Exceptions\ValidationException;
use Daraja\Tests\DarajaTestCase;

final class ConfigTest extends DarajaTestCase
{
    public function test_creates_valid_config(): void
    {
        $config = $this->makeConfig();

        self::assertSame('test_consumer_key', $config->consumerKey);
        self::assertSame('174379', $config->shortcode);
        self::assertTrue($config->isSandbox());
        self::assertSame('https://sandbox.safaricom.co.ke', $config->baseUrl());
    }

    public function test_production_environment_uses_correct_base_url(): void
    {
        $config = $this->makeConfig(['environment' => Environment::Production]);

        self::assertFalse($config->isSandbox());
        self::assertSame('https://api.safaricom.co.ke', $config->baseUrl());
    }

    public function test_throws_if_consumer_key_is_empty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Consumer key/');

        Config::make(
            consumerKey:    '',
            consumerSecret: 'secret',
            shortcode:      '174379',
            passkey:        'passkey',
        );
    }

    public function test_throws_if_consumer_secret_is_empty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Consumer secret/');

        Config::make(
            consumerKey:    'key',
            consumerSecret: '',
            shortcode:      '174379',
            passkey:        'passkey',
        );
    }

    public function test_throws_if_shortcode_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        Config::make(
            consumerKey:    'key',
            consumerSecret: 'secret',
            shortcode:      '',
            passkey:        'passkey',
        );
    }

    public function test_throws_if_passkey_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        Config::make(
            consumerKey:    'key',
            consumerSecret: 'secret',
            shortcode:      '174379',
            passkey:        '',
        );
    }

    public function test_validation_exception_contains_all_errors(): void
    {
        try {
            Config::make(
                consumerKey:    '',
                consumerSecret: '',
                shortcode:      '174379',
                passkey:        'passkey',
            );

            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('consumer_key', $e->errors());
            self::assertArrayHasKey('consumer_secret', $e->errors());
        }
    }
}
