<?php

declare(strict_types=1);

namespace Daraja\Laravel;

use Daraja\DarajaClient;
use Daraja\Enums\Environment;
use Daraja\Laravel\Commands\CheckBalance;
use Daraja\Laravel\Commands\GenerateSecurityCredential;
use Daraja\Laravel\Commands\RegisterC2BUrls;
use Daraja\Laravel\Http\Controllers\MpesaWebhookController;
use Daraja\Laravel\Http\Middleware\VerifyMpesaIp;
use Daraja\Webhooks\CallbackProcessor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * DarajaServiceProvider
 *
 * Auto-discovered via composer.json extra.laravel.providers.
 *
 * Registers:
 *   - DarajaClient singleton (resolved from config/daraja.php)
 *   - CallbackProcessor singleton
 *   - MpesaWebhookController singleton
 *   - 'mpesa.ip' middleware alias (VerifyMpesaIp)
 *   - All webhook routes under the configured prefix
 *   - Artisan commands: mpesa:generate-credential, mpesa:register-urls, mpesa:check-balance
 *
 * Publish config:
 *   php artisan vendor:publish --tag=daraja-config
 */
final class DarajaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/daraja.php', 'daraja');

        $this->app->singleton(DarajaClient::class, function (Application $app): DarajaClient {
            /** @var array<string, mixed> $cfg */
            $cfg = $app['config']['daraja'];

            return DarajaClient::make(
                consumerKey:        (string) ($cfg['consumer_key']        ?? ''),
                consumerSecret:     (string) ($cfg['consumer_secret']     ?? ''),
                shortcode:          (string) ($cfg['shortcode']           ?? ''),
                passkey:            (string) ($cfg['passkey']             ?? ''),
                environment:        Environment::from((string) ($cfg['environment'] ?? 'sandbox')),
                securityCredential: (string) ($cfg['security_credential'] ?? ''),
                initiatorName:      (string) ($cfg['initiator_name']      ?? ''),
                callbackUrl:        (string) ($cfg['callback_url']        ?? ''),
                resultUrl:          (string) ($cfg['result_url']          ?? ''),
                timeoutUrl:         (string) ($cfg['timeout_url']         ?? ''),
                timeout:            (int)    ($cfg['timeout']             ?? 30),
            );
        });

        $this->app->alias(DarajaClient::class, 'mpesa');
        $this->app->singleton(CallbackProcessor::class);
        $this->app->singleton(MpesaWebhookController::class);
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerCommands();
    }

    private function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/daraja.php' => config_path('daraja.php'),
            ], 'daraja-config');
        }
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('mpesa.ip', VerifyMpesaIp::class);
    }

    private function registerRoutes(): void
    {
        /** @var array<string, mixed> $routeConfig */
        $routeConfig = config('daraja.routes', []);

        if (!($routeConfig['register_routes'] ?? true)) {
            return;
        }

        $prefix     = (string) ($routeConfig['prefix']    ?? 'mpesa');
        $middleware = (array)  ($routeConfig['middleware'] ?? ['api']);
        $controller = MpesaWebhookController::class;

        $this->app['router']
            ->prefix($prefix)
            ->middleware([...$middleware, 'mpesa.ip'])
            ->group(function (Router $router) use ($controller): void {

                // STK Push
                $router->post('stk/callback',  [$controller, 'handleSTKCallback'])->name('mpesa.stk.callback');

                // C2B
                $router->post('c2b/validation',   [$controller, 'handleC2BValidation'])->name('mpesa.c2b.validation');
                $router->post('c2b/confirmation', [$controller, 'handleC2BConfirmation'])->name('mpesa.c2b.confirmation');

                // B2C
                $router->post('b2c/result',   [$controller, 'handleB2CResult'])->name('mpesa.b2c.result');
                $router->post('b2c/timeout',  [$controller, 'handleTimeout'])->name('mpesa.b2c.timeout');

                // B2B (also handles Tax Remittance results)
                $router->post('b2b/result',   [$controller, 'handleB2BResult'])->name('mpesa.b2b.result');
                $router->post('b2b/timeout',  [$controller, 'handleTimeout'])->name('mpesa.b2b.timeout');

                // Account Balance
                $router->post('balance/result',  [$controller, 'handleAccountBalance'])->name('mpesa.balance.result');
                $router->post('balance/timeout', [$controller, 'handleTimeout'])->name('mpesa.balance.timeout');

                // Transaction Status
                $router->post('status/result',  [$controller, 'handleTransactionStatus'])->name('mpesa.status.result');
                $router->post('status/timeout', [$controller, 'handleTimeout'])->name('mpesa.status.timeout');

                // Reversal
                $router->post('reversal/result',  [$controller, 'handleReversal'])->name('mpesa.reversal.result');
                $router->post('reversal/timeout', [$controller, 'handleTimeout'])->name('mpesa.reversal.timeout');

                // Tax Remittance (result comes via B2B endpoint)
                $router->post('tax/result',  [$controller, 'handleB2BResult'])->name('mpesa.tax.result');
                $router->post('tax/timeout', [$controller, 'handleTimeout'])->name('mpesa.tax.timeout');

                // Bill Manager
                $router->post('bill/reconciliation', [$controller, 'handleBillManagerReconciliation'])->name('mpesa.bill.reconciliation');
            });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSecurityCredential::class,
                RegisterC2BUrls::class,
                CheckBalance::class,
            ]);
        }
    }
}
