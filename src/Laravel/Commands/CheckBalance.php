<?php

declare(strict_types=1);

namespace Daraja\Laravel\Commands;

use Daraja\DarajaClient;
use Daraja\Enums\IdentifierType;
use Illuminate\Console\Command;

/**
 * Artisan command to trigger an Account Balance query.
 * Result arrives asynchronously at your resultUrl.
 *
 * Usage:
 *   php artisan mpesa:check-balance
 *   php artisan mpesa:check-balance --type=till
 */
final class CheckBalance extends Command
{
    protected $signature = 'mpesa:check-balance
        {--type=shortcode : Identifier type — shortcode, till, or msisdn}';

    protected $description = 'Query the M-Pesa account balance. Result is delivered to your Result URL.';

    public function __construct(private readonly DarajaClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $typeStr = strtolower((string) ($this->option('type') ?: 'shortcode'));

        $identifierType = match ($typeStr) {
            'till'      => IdentifierType::TillNumber,
            'msisdn'    => IdentifierType::MSISDN,
            default     => IdentifierType::Shortcode,
        };

        $this->info('Requesting account balance from Daraja...');
        $this->line("  Shortcode:       " . config('daraja.shortcode'));
        $this->line("  Identifier type: {$typeStr}");
        $this->line("  Result URL:      " . config('daraja.result_url'));
        $this->newLine();

        try {
            $response = $this->client->accountBalance()->query(
                identifierType: $identifierType,
                remarks:        'CLI balance check',
            );

            $this->info('✓ Balance request accepted by Daraja.');
            $this->line("  Conversation ID: " . $response->conversationId());
            $this->line("  The result will be delivered to your configured Result URL.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Balance query failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
