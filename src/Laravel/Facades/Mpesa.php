<?php

declare(strict_types=1);

namespace Daraja\Laravel\Facades;

use Daraja\DarajaClient;
use Daraja\Services\AccountBalance;
use Daraja\Services\B2BService;
use Daraja\Services\B2CService;
use Daraja\Services\C2BService;
use Daraja\Services\DynamicQR;
use Daraja\Services\Reversal;
use Daraja\Services\STKPush;
use Daraja\Services\TransactionStatus;
use Illuminate\Support\Facades\Facade;

/**
 * Mpesa Facade.
 *
 * Provides static access to the DarajaClient bound in the IoC container.
 *
 * @method static STKPush           stk()
 * @method static C2BService        c2b()
 * @method static B2CService        b2c()
 * @method static B2BService        b2b()
 * @method static TransactionStatus transactionStatus()
 * @method static AccountBalance    accountBalance()
 * @method static Reversal          reversal()
 * @method static DynamicQR         qr()
 *
 * @see DarajaClient
 */
final class Mpesa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DarajaClient::class;
    }
}
