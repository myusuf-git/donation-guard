# Donation Guard

**Find donations stuck in "pending" and check their real status with Southeast Asian payment gateways.**

A WordPress plugin for nonprofits and fundraising sites that use **WooCommerce** or **GiveWP** with **Midtrans**, **Xendit** or **Tripay**.

![license](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
![php](https://img.shields.io/badge/php-7.4%2B-777bb4)

![Donation Guard demo: pending donations checked against Midtrans, Xendit and Tripay, then fixed](docs/demo.gif)

## The problem

Payment webhooks get lost: the server was down, a firewall blocked the callback, the plugin was updated mid-campaign. The donor **has paid**, but the site still shows **pending**:

- the donor gets no receipt and may donate twice, or lose trust;
- campaign totals and progress bars are wrong;
- reports to the board and to donors don't match the bank;
- someone in finance spends hours comparing gateway dashboards with spreadsheets.

Donation Guard does that comparison for you, every hour, and keeps a log of every decision.

## What it does

| Gateway says | Donation Guard does |
|---|---|
| Paid, same amount | Marks the donation as paid (WooCommerce `payment_complete`, GiveWP `publish`) |
| Paid, **different amount** | Changes nothing, flags it for a human |
| Expired / failed / cancelled | Marks as failed (can be turned off) |
| Still pending | Leaves it |
| Refunded, not found, API error | Changes nothing, flags it for a human |

Safety first:

- **Dry-run is on by default.** It only reports until you switch it off.
- It only ever *reads* from gateways. It never creates, captures or refunds a payment.
- An amount mismatch is never fixed automatically.
- Every check is written to an audit log (exportable as CSV).
- API keys can live in `wp-config.php` instead of the database, and are never printed back in the admin.

## Supported

| | |
|---|---|
| Donation plugins | WooCommerce (incl. HPOS), GiveWP |
| Gateways | Midtrans (Core API status), Xendit (Invoices), Tripay |
| Runs | WP-Cron (hourly / twice daily / daily), "Run now" button, WP-CLI |
| Reports | Admin dashboard, email, JSON webhook (Slack, n8n, Telegram/WhatsApp bridges) |
| Requirements | WordPress 6.0+, PHP 7.4+ |

Want another gateway (DOKU, PayMongo, Omise, Billplz, ...)? It is one PHP file. See [CONTRIBUTING.md](CONTRIBUTING.md).

## Install

1. Download the latest zip from [Releases](https://github.com/myusuf-git/donation-guard/releases) and upload it in **Plugins → Add New → Upload**.
2. Go to **Tools → Donation Guard → Settings**.
3. Enable your gateway, paste the API key (or define it in `wp-config.php`, see below).
4. Click **Run dry-run** on the dashboard and read the report.
5. When the report looks right, untick **Dry-run**.

```php
// wp-config.php (recommended)
define('DONATION_GUARD_MIDTRANS_SECRET', 'Mid-server-xxxx');
define('DONATION_GUARD_XENDIT_SECRET',   'xnd_production_xxxx');
define('DONATION_GUARD_TRIPAY_SECRET',   'xxxx');
```

### Which reference does it use?

Each gateway knows a payment by a reference. By default Donation Guard uses the WooCommerce order number, or GiveWP's transaction id. If your payment plugin stores the gateway reference in another meta field, put that meta key in **Reference meta key**.

| Gateway | Reference it expects |
|---|---|
| Midtrans | the `order_id` you sent to Midtrans |
| Xendit | the invoice `external_id` |
| Tripay | Tripay's `reference` (e.g. `T0001...`), not your `merchant_ref` |

The gateway is detected from the payment method id (anything containing `midtrans`, `xendit` or `tripay`). Override with the `donation_guard_detect_gateway` filter.

## WP-CLI

```bash
wp donation-guard run --dry-run      # report only
wp donation-guard run --live         # apply changes
wp donation-guard run --format=json
wp donation-guard log --limit=20
```

## Hooks

| Hook | Use |
|---|---|
| `donation_guard_detect_gateway` (filter) | Map a custom payment method id to `midtrans` / `xendit` / `tripay` |
| `donation_guard_woocommerce_donation` (filter) | Adjust the reference / amount read from an order |
| `donation_guard_givewp_donation` (filter) | Same for GiveWP |
| `donation_guard_gateway_base_url` (filter) | Point a gateway to a proxy or a mock server |
| `donation_guard_after_run` (action) | Receive the run summary |

## Roadmap

- **v0.2** Honest ad conversions: send Meta Conversions API / Google Ads offline conversions only for donations that are *really* paid, and report the gap between "ad-reported" and "actually paid" donations.
- **v0.3** Standalone CLI / Docker for non-WordPress donation sites; generic custom-table adapter.
- More gateways: DOKU, PayMongo (PH), Omise (TH), Billplz (MY).
- Publish to the wordpress.org plugin directory.

## Development

```bash
composer install
vendor/bin/phpunit
```

Gateway tests run against recorded JSON fixtures in `tests/fixtures/`, so no API keys are needed.

## Background

Built from real-world experience running donation sites in Indonesia, where most campaigns run on WordPress and payments go through local gateways. [Panduan Bahasa Indonesia](docs/id/README.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
