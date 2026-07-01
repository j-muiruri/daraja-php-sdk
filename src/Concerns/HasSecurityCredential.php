<?php

declare(strict_types=1);

namespace Daraja\Concerns;

use Daraja\Exceptions\DarajaException;

/**
 * Provides security credential generation for operator-facing APIs.
 *
 * B2C, B2B, Reversal, and Account Balance require a `SecurityCredential`:
 * the initiator's password encrypted with Safaricom's public certificate,
 * then Base64-encoded.
 *
 * Safaricom provides two certificates:
 *   - Sandbox: https://developer.safaricom.co.ke/sites/default/files/cert/sandbox/cert.cer
 *   - Production: https://developer.safaricom.co.ke/sites/default/files/cert/prod/cert.cer
 *
 * Download and store these locally; pass the path via Config::$securityCredential
 * OR pass the pre-generated Base64 credential string directly.
 */
trait HasSecurityCredential
{
    /**
     * Encrypt an initiator password using Safaricom's public certificate.
     *
     * @param  string $initiatorPassword  Plain-text password from Daraja portal
     * @param  string $certificatePath    Absolute path to the .cer file
     * @throws DarajaException
     */
    protected function generateSecurityCredential(
        string $initiatorPassword,
        string $certificatePath,
    ): string {
        if (!file_exists($certificatePath)) {
            throw new DarajaException(
                "Safaricom certificate not found at: {$certificatePath}"
            );
        }

        $certContent = file_get_contents($certificatePath);

        if ($certContent === false) {
            throw new DarajaException(
                "Unable to read certificate file: {$certificatePath}"
            );
        }

        $publicKey = openssl_pkey_get_public($certContent);

        if ($publicKey === false) {
            throw new DarajaException(
                'Failed to extract public key from certificate. Ensure the file is a valid .cer'
            );
        }

        $encrypted = '';
        $success   = openssl_public_encrypt(
            $initiatorPassword,
            $encrypted,
            $publicKey,
            OPENSSL_PKCS1_PADDING
        );

        if (!$success) {
            throw new DarajaException(
                'Failed to encrypt initiator password: ' . openssl_error_string()
            );
        }

        return base64_encode($encrypted);
    }
}
