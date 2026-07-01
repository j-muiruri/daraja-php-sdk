<?php

declare(strict_types=1);

namespace Daraja\Enums;

enum QRCodeType: string
{
    case DynamicMerchant = 'DynamicMerchant';  // For Buy Goods (till)
    case StaticMerchant  = 'StaticMerchant';   // For Paybill (always same amount)
    case DynamicAccount  = 'DynamicAccount';   // Paybill + dynamic amount + account
    case StaticAccount   = 'StaticAccount';    // Paybill + fixed amount + account
}
