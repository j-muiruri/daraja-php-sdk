<?php

declare(strict_types=1);

namespace Daraja\Enums;

/**
 * Execution frequency for an M-Pesa Ratiba standing order.
 *
 * @see https://developer.safaricom.co.ke/apis/MpesaRatiba
 */
enum Frequency: int
{
    case OneOff     = 1;
    case Daily      = 2;
    case Weekly     = 3;
    case Monthly    = 4;
    case BiMonthly  = 5;
    case Quarterly  = 6;
    case HalfYear   = 7;
    case Yearly     = 8;
}
