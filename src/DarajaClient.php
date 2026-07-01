<?php

declare(strict_types=1);

namespace Daraja;

use Daraja\Auth\AccessTokenManager;
use Daraja\Auth\Cache\TokenCacheInterface;
use Daraja\Enums\Environment;
use Daraja\Http\HttpClient;
use Daraja\Services\AccountBalance;
use Daraja\Services\B2BService;
use Daraja\Services\B2CService;
use Daraja\Services\BillManager;
use Daraja\Services\C2BService;
use Daraja\Services\DynamicQR;
use Daraja\Services\Reversal;
use Daraja\Services\STKPush;
use Daraja\Services\TaxRemittance;
use Daraja\Services\TransactionStatus;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Main entry point for the Daraja M-Pesa PHP SDK.
 *
 * Covers the full Daraja API suite:
 *   stk()               — Lipa na M-Pesa Online (STK Push + Query)
 *   c2b()               — C2B Register URLs + Simulate
 *   b2c()               — Business to Customer disbursements
 *   b2b()               — Business to Business payments
 *   transactionStatus() — Transaction Status query
 *   accountBalance()    — Account Balance query
 *   reversal()          — Transaction Reversal
 *   qr()                — Dynamic QR Code generation
 *   taxRemittance()     — Tax payment to KRA (PayTaxToKRA)
 *   billManager()       — Bill Manager (eBill) invoicing
 *
 * Usage:
 *   $mpesa = DarajaClient::make(
 *       consumerKey:    $_ENV['MPESA_CONSUMER_KEY'],
 *       consumerSecret: $_ENV['MPESA_CONSUMER_SECRET'],
 *       shortcode:      $_ENV['MPESA_SHORTCODE'],
 *       passkey:        $_ENV['MPESA_PASSKEY'],
 *   );
 *
 *   $mpesa->stk()->push('0712345678', 1500, 'INV-001', callbackUrl: 'https://...');
 *   $mpesa->b2c()->sendSalary('0712345678', 45000);
 *   $mpesa->taxRemittance()->remit(50000, 'PRN12345678');
 *   $mpesa->billManager()->sendInvoice($invoice);
 */
final class DarajaClient
{
    private readonly AccessTokenManager $tokenManager;
    private readonly HttpClient         $httpClient;

    private ?STKPush           $stkPushService    = null;
    private ?C2BService        $c2bService        = null;
    private ?B2CService        $b2cService        = null;
    private ?B2BService        $b2bService        = null;
    private ?TransactionStatus $transactionSvc    = null;
    private ?AccountBalance    $accountBalanceSvc = null;
    private ?Reversal          $reversalSvc       = null;
    private ?DynamicQR         $dynamicQRSvc      = null;
    private ?TaxRemittance     $taxRemittanceSvc  = null;
    private ?BillManager       $billManagerSvc    = null;

    public function __construct(
        private readonly Config          $config,
        ?TokenCacheInterface             $tokenCache = null,
    ) {
        $guzzle             = new GuzzleClient(['timeout' => $config->timeout]);
        $this->tokenManager = new AccessTokenManager($config, $guzzle, $tokenCache);
        $this->httpClient   = new HttpClient($config, $this->tokenManager, $guzzle);
    }

    // -------------------------------------------------------------------------
    // Factory helpers
    // -------------------------------------------------------------------------

    public static function make(
        string               $consumerKey,
        string               $consumerSecret,
        string               $shortcode,
        string               $passkey,
        Environment          $environment = Environment::Sandbox,
        string               $securityCredential = '',
        string               $initiatorName = '',
        string               $callbackUrl = '',
        string               $resultUrl = '',
        string               $timeoutUrl = '',
        int                  $timeout = 30,
        ?TokenCacheInterface $tokenCache = null,
    ): self {
        return new self(
            config: Config::make(
                consumerKey:        $consumerKey,
                consumerSecret:     $consumerSecret,
                shortcode:          $shortcode,
                passkey:            $passkey,
                environment:        $environment,
                securityCredential: $securityCredential,
                initiatorName:      $initiatorName,
                callbackUrl:        $callbackUrl,
                resultUrl:          $resultUrl,
                timeoutUrl:         $timeoutUrl,
                timeout:            $timeout,
            ),
            tokenCache: $tokenCache,
        );
    }

    public static function fromConfig(Config $config, ?TokenCacheInterface $tokenCache = null): self
    {
        return new self($config, $tokenCache);
    }

    /**
     * Create from environment variables.
     *
     * Reads: MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, MPESA_SHORTCODE, MPESA_PASSKEY,
     *        MPESA_ENVIRONMENT, MPESA_SECURITY_CREDENTIAL, MPESA_INITIATOR_NAME,
     *        MPESA_CALLBACK_URL, MPESA_RESULT_URL, MPESA_TIMEOUT_URL
     */
    public static function fromEnv(?TokenCacheInterface $tokenCache = null): self
    {
        $env = strtolower((string) ($_ENV['MPESA_ENVIRONMENT'] ?? getenv('MPESA_ENVIRONMENT') ?: 'sandbox'));

        return self::make(
            consumerKey:        (string) ($_ENV['MPESA_CONSUMER_KEY']        ?? getenv('MPESA_CONSUMER_KEY')        ?: ''),
            consumerSecret:     (string) ($_ENV['MPESA_CONSUMER_SECRET']      ?? getenv('MPESA_CONSUMER_SECRET')      ?: ''),
            shortcode:          (string) ($_ENV['MPESA_SHORTCODE']            ?? getenv('MPESA_SHORTCODE')            ?: ''),
            passkey:            (string) ($_ENV['MPESA_PASSKEY']              ?? getenv('MPESA_PASSKEY')              ?: ''),
            environment:        Environment::from($env),
            securityCredential: (string) ($_ENV['MPESA_SECURITY_CREDENTIAL'] ?? getenv('MPESA_SECURITY_CREDENTIAL') ?: ''),
            initiatorName:      (string) ($_ENV['MPESA_INITIATOR_NAME']       ?? getenv('MPESA_INITIATOR_NAME')       ?: ''),
            callbackUrl:        (string) ($_ENV['MPESA_CALLBACK_URL']         ?? getenv('MPESA_CALLBACK_URL')         ?: ''),
            resultUrl:          (string) ($_ENV['MPESA_RESULT_URL']           ?? getenv('MPESA_RESULT_URL')           ?: ''),
            timeoutUrl:         (string) ($_ENV['MPESA_TIMEOUT_URL']          ?? getenv('MPESA_TIMEOUT_URL')          ?: ''),
            tokenCache:         $tokenCache,
        );
    }

    // -------------------------------------------------------------------------
    // Service accessors (lazy singletons)
    // -------------------------------------------------------------------------

    /** Lipa na M-Pesa Online — STK Push and STK Query */
    public function stk(): STKPush
    {
        return $this->stkPushService ??= new STKPush($this->config, $this->httpClient);
    }

    /** Customer to Business — register URLs and simulate payments */
    public function c2b(): C2BService
    {
        return $this->c2bService ??= new C2BService($this->config, $this->httpClient);
    }

    /** Business to Customer — salary, promotion, and business payments */
    public function b2c(): B2CService
    {
        return $this->b2cService ??= new B2CService($this->config, $this->httpClient);
    }

    /** Business to Business — paybill and buy goods transfers */
    public function b2b(): B2BService
    {
        return $this->b2bService ??= new B2BService($this->config, $this->httpClient);
    }

    /** Transaction Status — query any M-Pesa transaction by receipt number */
    public function transactionStatus(): TransactionStatus
    {
        return $this->transactionSvc ??= new TransactionStatus($this->config, $this->httpClient);
    }

    /** Account Balance — query available balance on your shortcode */
    public function accountBalance(): AccountBalance
    {
        return $this->accountBalanceSvc ??= new AccountBalance($this->config, $this->httpClient);
    }

    /** Transaction Reversal — reverse a completed M-Pesa transaction */
    public function reversal(): Reversal
    {
        return $this->reversalSvc ??= new Reversal($this->config, $this->httpClient);
    }

    /** Dynamic QR — generate scannable M-Pesa payment QR codes */
    public function qr(): DynamicQR
    {
        return $this->dynamicQRSvc ??= new DynamicQR($this->config, $this->httpClient);
    }

    /** Tax Remittance — remit taxes directly to KRA via M-Pesa */
    public function taxRemittance(): TaxRemittance
    {
        return $this->taxRemittanceSvc ??= new TaxRemittance($this->config, $this->httpClient);
    }

    /** Bill Manager (eBill) — send and manage M-Pesa invoices to customers */
    public function billManager(): BillManager
    {
        return $this->billManagerSvc ??= new BillManager($this->config, $this->httpClient);
    }

    public function config(): Config
    {
        return $this->config;
    }
}
