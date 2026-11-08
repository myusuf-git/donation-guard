# Contributing

Thanks for helping nonprofits keep their numbers honest.

## Add a payment gateway (the most wanted contribution)

1. Create `src/Gateways/YourGateway.php` extending `AbstractGateway` and implementing `checkStatus(string $reference): Status`.
2. Map every gateway status to one of `Status::PAID | PENDING | EXPIRED | FAILED | REFUNDED | NOT_FOUND | ERROR`. When unsure, return `ERROR`; Donation Guard never changes a donation on `ERROR`.
3. Return the amount the gateway reports, so mismatches can be caught.
4. Save real (anonymised) API responses in `tests/fixtures/yourgateway/` and add `tests/Unit/Gateways/YourGatewayTest.php`.
5. Register it in `src/WP/Settings.php` (`GATEWAYS`) and `src/WP/Runner.php`.

## Rules

- No real API keys, donor names, emails or phone numbers in code, fixtures or issues.
- `composer install && vendor/bin/phpunit` must pass on PHP 7.4 and 8.x.
- Keep the safety rules in `src/Core/Reconciler.php`; changes there need a test.
- Small pull requests are easier to review.

## Reporting bugs

Please include: WordPress, PHP, WooCommerce/GiveWP versions, the payment plugin you use, and the relevant rows from **Tools → Donation Guard → Log** (remove personal data).
