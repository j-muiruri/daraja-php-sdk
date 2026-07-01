<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Auth;

use Daraja\Auth\AccessToken;
use PHPUnit\Framework\TestCase;

final class AccessTokenTest extends TestCase
{
    public function test_token_is_not_expired_when_fresh(): void
    {
        $token = new AccessToken('fake_token', 3600);

        self::assertFalse($token->isExpired());
        self::assertSame('fake_token', $token->value());
    }

    public function test_token_is_expired_when_ttl_elapsed(): void
    {
        // expiresIn of 0 → expiresAt = time() - 60, which is already past
        $token = new AccessToken('fake_token', 0);

        self::assertTrue($token->isExpired());
    }

    public function test_token_includes_60s_buffer(): void
    {
        // With 61s TTL, expiresAt = time() + 1 → not yet expired
        $token = new AccessToken('fake_token', 61);

        self::assertFalse($token->isExpired());
    }

    public function test_bearer_header_format(): void
    {
        $token = new AccessToken('my_secret_token', 3600);

        self::assertSame('Bearer my_secret_token', $token->bearerHeader());
    }

    public function test_expires_at_is_within_expected_range(): void
    {
        $before = time();
        $token  = new AccessToken('t', 3600);
        $after  = time();

        // expiresAt = time() + 3600 - 60 = time() + 3540
        self::assertGreaterThanOrEqual($before + 3540, $token->expiresAt());
        self::assertLessThanOrEqual($after + 3540, $token->expiresAt());
    }
}
