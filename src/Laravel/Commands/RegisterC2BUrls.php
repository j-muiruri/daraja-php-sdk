<?php

declare(strict_types=1);

namespace Daraja\Laravel\Commands;

use Daraja\DarajaClient;
use Illuminate\Console\Command;

/**
 * Artisan command to register C2B Validation and Confirmation URLs with Daraja.
 *
 * Usage:
 *   php artisan mpesa:register-urls
 *   php artisan mpesa:register-urls --confirmation=https://app.example.com/mpesa/c2b/confirmation
 *   php artisan mpesa:register-urls --shortcode=600001
 */
final class RegisterC2BUrls extends Command
{
    protected $signature = 'mpesa:register-urls
        {--confirmation= : Confirmation URL (defaults to config daraja.callback_url)}
        {--validation=   : Validation URL (defaults to confirmation URL)}
        {--shortcode=    : Override business shortcode}
        {--response=Completed : ResponseType — Completed or Cancelled}';

    protected $description = 'Register M-Pesa C2B Validation and Confirmation URLs with Daraja.';

    public function __construct(private readonly DarajaClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $confirmationUrl = (string) ($this->option('confirmation') ?: config('daraja.callback_url'));
        $validationUrl   = (string) ($this->option('validation')   ?: $confirmationUrl);
        $shortcode       = $this->option('shortcode') ? (string) $this->option('shortcode') : null;
        $responseType    = (string) ($this->option('response') ?: 'Completed');

        if (empty($confirmationUrl)) {
            $this->error('Confirmation URL is required. Set MPESA_CALLBACK_URL in .env or pass --confirmation=<url>');
            return self::FAILURE;
        }

        $this->info('Registering C2B URLs with Daraja...');
        $this->line("  Confirmation: {$confirmationUrl}");
        $this->line("  Validation:   {$validationUrl}");
        $this->line("  Shortcode:    " . ($shortcode ?? config('daraja.shortcode')));
        $this->line("  ResponseType: {$responseType}");
        $this->newLine();

        try {
            $response = $this->client->c2b()->registerUrls(
                confirmationUrl: $confirmationUrl,
                validationUrl:   $validationUrl,
                responseType:    $responseType,
                shortcode:       $shortcode,
            );

            if ($response->isSuccessful()) {
                $this->info('✓ C2B URLs registered successfully!');
                $this->line("  Response: " . $response->responseDescription());
                return self::SUCCESS;
            }

            $this->error('Daraja returned an error: ' . $response->responseDescription());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Registration failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
