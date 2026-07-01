<?php

declare(strict_types=1);

namespace Daraja\Enums;

/**
 * CommandId values used across B2C, B2B, Tax Remittance, Transaction Status, and Reversal APIs.
 */
enum CommandId: string
{
    // B2C
    case SalaryPayment          = 'SalaryPayment';
    case BusinessPayment        = 'BusinessPayment';
    case PromotionPayment       = 'PromotionPayment';

    // B2B
    case BusinessPayBill            = 'BusinessPayBill';
    case MerchantToMerchant         = 'MerchantToMerchantTransfer';
    case BusinessBuyGoods           = 'BusinessBuyGoods';
    case DisburseFundsToBusiness    = 'DisburseFundsToBusiness';
    case BusinessToBusinessTransfer = 'BusinessToBusinessTransfer';

    // Tax Remittance (B2B to KRA)
    case PayTaxToKRA            = 'PayTaxToKRA';

    // Transaction Status
    case TransactionStatusQuery = 'TransactionStatusQuery';

    // Account Balance
    case AccountBalance         = 'AccountBalance';

    // Reversal
    case TransactionReversal    = 'TransactionReversal';

    // C2B
    case CustomerPayBillOnline  = 'CustomerPayBillOnline';
    case CustomerBuyGoodsOnline = 'CustomerBuyGoodsOnline';
}
