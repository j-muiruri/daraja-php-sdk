<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\ValueObjects;

use Daraja\Exceptions\ValidationException;
use Daraja\ValueObjects\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    #[DataProvider('validPhoneProvider')]
    public function test_normalises_valid_kenyan_numbers(string $input, string $expected): void
    {
        $phone = PhoneNumber::from($input);

        self::assertSame($expected, $phone->value());
        self::assertSame($expected, (string) $phone);
    }

    /** @return array<string, array{string, string}> */
    public static function validPhoneProvider(): array
    {
        return [
            'local 07 format'       => ['0712345678', '254712345678'],
            'local 07 with spaces'  => ['0712 345 678', '254712345678'],
            'e164 with plus'        => ['+254712345678', '254712345678'],
            'e164 without plus'     => ['254712345678', '254712345678'],
            'short 9 digit'         => ['712345678', '254712345678'],
            'safaricom 07'          => ['0722123456', '254722123456'],
            'airtel 07'             => ['0733123456', '254733123456'],
            'leading/trailing space' => ['  0712345678  ', '254712345678'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_rejects_invalid_numbers(string $input): void
    {
        $this->expectException(ValidationException::class);

        PhoneNumber::from($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidPhoneProvider(): array
    {
        return [
            'too short'           => ['071234'],
            'too long'            => ['07123456789123'],
            'wrong country code'  => ['255712345678'],
            'non-Safaricom local' => ['0512345678'],
            'empty string'        => [''],
            'letters'             => ['0712ABCDEF'],
            'us number'           => ['+14155552671'],
        ];
    }

    public function test_bearer_header_format(): void
    {
        $phone = PhoneNumber::from('0712345678');

        self::assertSame('254712345678', $phone->value());
    }

    public function test_static_from_constructor(): void
    {
        $a = PhoneNumber::from('0712345678');
        $b = new PhoneNumber('0712345678');

        self::assertSame($a->value(), $b->value());
    }
}
