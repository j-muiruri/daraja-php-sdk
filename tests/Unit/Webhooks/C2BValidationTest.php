<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Webhooks;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\AccountBalanceResult;
use Daraja\Webhooks\Payloads\B2CResult;
use Daraja\Webhooks\Payloads\C2BConfirmation;
use Daraja\Webhooks\Payloads\C2BValidation;
use Daraja\Webhooks\Payloads\ReversalResult;
use Daraja\Webhooks\Payloads\STKCallback;
use Daraja\Webhooks\Payloads\TransactionStatusResult;
use PHPUnit\Framework\TestCase;

// ============================================================
// STKCallback

final class C2BValidationTest extends TestCase
{
    public function test_accept_response_has_correct_structure(): void
    {
        $response = C2BValidation::accept();

        self::assertSame('0', $response['ResultCode']);
        self::assertSame('Accepted', $response['ResultDesc']);
    }

    public function test_reject_response_has_correct_structure(): void
    {
        $response = C2BValidation::reject();

        self::assertSame(C2BValidation::REJECT_CODE_GENERAL, $response['ResultCode']);
        self::assertSame('Rejected', $response['ResultDesc']);
    }

    public function test_reject_with_custom_code(): void
    {
        $response = C2BValidation::reject(
            C2BValidation::REJECT_CODE_INVALID_ACCOUNT,
            'Unknown account',
        );

        self::assertSame(C2BValidation::REJECT_CODE_INVALID_ACCOUNT, $response['ResultCode']);
        self::assertSame('Unknown account', $response['ResultDesc']);
    }
}
