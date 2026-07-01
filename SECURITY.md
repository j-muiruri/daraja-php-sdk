# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ Active  |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Email: [security@daraja-sdk.dev](mailto:security@daraja-sdk.dev)

Include:

- SDK version
- Description of the vulnerability
- Steps to reproduce
- Potential impact

We will respond within 72 hours and aim to release a patch within 7 days
of confirmed vulnerabilities.

## Security Best Practices

When using this SDK:

1. **Never commit credentials** — use environment variables
2. **Restrict callback URLs** — use HTTPS exclusively
3. **Enable the IP whitelist** — keep `config/daraja.php` `safaricom_ips` populated
4. **Rotate credentials** — regenerate consumer keys if compromised
5. **Store SecurityCredential encrypted** — treat it like a password
6. **Use idempotency** — check `MpesaReceiptNumber` before crediting accounts
7. **Use Redis token caching** — avoids unnecessary OAuth calls
