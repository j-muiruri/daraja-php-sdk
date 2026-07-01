# Contributing

Thank you for considering contributing to the Daraja M-Pesa PHP SDK.

## Development Setup

```bash
git clone https://github.com/j-muiruri/daraja-php-sdk.git
cd mpesa-php
composer install
```

## Running Tests

```bash
composer test          # All tests
composer test:unit     # Unit tests only
composer test:feature  # Feature tests only
composer analyse       # PHPStan level 8
```

## Standards

- **PHP 8.2+** with strict types declared in every file
- **PSR-12** coding style
- **PHPStan level 8** — all code must pass static analysis
- **100% new code must be tested** — no PRs without tests
- All public methods must have PHPDoc blocks
- Use readonly properties on immutable objects

## Submitting a Pull Request

1. Fork and create a branch: `git checkout -b feature/my-feature`
2. Write tests first (TDD preferred)
3. Implement the feature
4. Run the full CI suite: `composer ci`
5. Update `CHANGELOG.md` under `[Unreleased]`
6. Open a PR against `develop`

## Reporting Bugs

Open an issue with the API name, request payload (sanitised — no real credentials),
and the full exception message/stack trace.
