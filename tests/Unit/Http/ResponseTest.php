<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Http;

use Daraja\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_is_accepted_requires_a_success_code(): void
    {
        self::assertFalse(Response::fromArray(['ResponseDescription' => 'Accepted'])->isAccepted());
    }

    public function test_is_accepted_supports_common_success_codes(): void
    {
        self::assertTrue(Response::fromArray(['ResponseCode' => '0'])->isAccepted());
        self::assertTrue(Response::fromArray(['ResponseCode' => '00'])->isAccepted());
        self::assertTrue(Response::fromArray(['ResultCode' => 0])->isAccepted());
    }
}
