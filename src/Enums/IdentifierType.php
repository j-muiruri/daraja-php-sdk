<?php

declare(strict_types=1);

namespace Daraja\Enums;

/**
 * Identifier types used in Transaction Status and Account Balance APIs.
 */
enum IdentifierType: int
{
    case MSISDN     = 1;  // Phone number
    case TillNumber = 2;  // Buy Goods till number
    case Shortcode  = 4;  // Paybill shortcode
}
