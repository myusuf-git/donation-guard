# Changelog

## 0.2.0 - 2026-11-08

- New source: **DonasiAja** (2.2.x). Paid donations are confirmed through DonasiAja itself, so donors still get their receipt. Expired ones are reported, not changed.
- New: **callback health check**. Probes your webhook URLs every run and reports timeouts, 5xx, redirects, firewall blocks and non-JSON answers.
- New outcome **"No payment code issued"** for donations where the gateway never created an invoice.
- New setting **Look back (days)**: only checks donations from the last N days (default 7).
- Plugins without a failed status: expired donations are remembered and not asked again every run.
- Fix: GiveWP donations with an empty GMT date were never checked.
- Fix: one failing update no longer stops the whole run.

## 0.1.0 - 2026-11-08

First public release.

- Reconcile pending WooCommerce orders and GiveWP donations against Midtrans, Xendit and Tripay.
- Dry-run by default; amount-mismatch protection; optional auto-fail for expired payments.
- Admin dashboard, settings, audit log with CSV export.
- WP-Cron schedule, "Run now" buttons, WP-CLI commands.
- Email and JSON webhook reports.
