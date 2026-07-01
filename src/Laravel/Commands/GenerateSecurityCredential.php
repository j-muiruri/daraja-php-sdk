<?php

declare(strict_types=1);

namespace Daraja\Laravel\Commands;

use Daraja\Concerns\HasSecurityCredential;
use Illuminate\Console\Command;

/**
 * Artisan command to generate the SecurityCredential value required by
 * B2C, B2B, Reversal, and Account Balance APIs.
 *
 * Usage:
 *   php artisan mpesa:generate-credential
 *   php artisan mpesa:generate-credential --password=MyPassword --cert=/path/to/cert.cer
 *
 * Output the value and offer to write it to .env automatically.
 */
final class GenerateSecurityCredential extends Command
{
    use HasSecurityCredential;

    protected $signature = 'mpesa:generate-credential
        {--password= : Initiator password (will prompt if not set)}
        {--cert=     : Absolute path to Safaricom certificate (.cer file)}
        {--env        : Write the generated credential to your .env file automatically}';

    protected $description = 'Generate the M-Pesa SecurityCredential by encrypting your initiator password with the Safaricom certificate.';

    public function handle(): int
    {
        $this->info('M-Pesa Security Credential Generator');
        $this->line('─────────────────────────────────────');

        // Resolve password
        $password = (string) ($this->option('password') ?: $this->secret('Enter your Daraja initiator password'));

        if (empty($password)) {
            $this->error('Initiator password is required.');
            return self::FAILURE;
        }

        // Resolve certificate path
        $certPath = (string) ($this->option('cert') ?: $this->ask(
            'Enter the absolute path to the Safaricom certificate (.cer)',
            $this->defaultCertPath(),
        ));

        if (!file_exists($certPath)) {
            $this->error("Certificate file not found: {$certPath}");
            $this->line('');
            $this->line('Download from:');
            $this->line('  Sandbox:    https://developer.safaricom.co.ke/sites/default/files/cert/sandbox/cert.cer');
            $this->line('  Production: https://developer.safaricom.co.ke/sites/default/files/cert/prod/cert.cer');
            return self::FAILURE;
        }

        try {
            $credential = $this->generateSecurityCredential($password, $certPath);
        } catch (\Throwable $e) {
            $this->error('Failed to generate credential: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✓ Security credential generated successfully!');
        $this->newLine();
        $this->line('<comment>MPESA_SECURITY_CREDENTIAL=</comment>' . $credential);
        $this->newLine();

        if ($this->option('env') || $this->confirm('Write MPESA_SECURITY_CREDENTIAL to your .env file?', false)) {
            $this->writeToEnv($credential);
        } else {
            $this->line('Copy the value above and set it as MPESA_SECURITY_CREDENTIAL in your .env file.');
        }

        return self::SUCCESS;
    }

    private function writeToEnv(string $credential): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env file not found at ' . $envPath);
            return;
        }

        $content = file_get_contents($envPath);
        $key     = 'MPESA_SECURITY_CREDENTIAL';
        $line    = "{$key}={$credential}";

        if (str_contains((string) $content, $key . '=')) {
            // Replace existing value
            $updated = preg_replace("/^{$key}=.*/m", $line, (string) $content);
            file_put_contents($envPath, $updated);
            $this->info("✓ Updated {$key} in .env");
        } else {
            // Append new key
            file_put_contents($envPath, (string) $content . PHP_EOL . $line . PHP_EOL);
            $this->info("✓ Added {$key} to .env");
        }
    }

    private function defaultCertPath(): string
    {
        $env = config('daraja.environment', 'sandbox');

        return base_path("certs/safaricom_{$env}.cer");
    }
}
