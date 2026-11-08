=== Donation Guard ===
Contributors: donationguard
Tags: donations, reconciliation, midtrans, xendit, donasiaja
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find donations stuck in "pending" and check their real status with Midtrans, Xendit and Tripay.

== Description ==

Payment webhooks get lost. The donor has paid, but your site still says pending. Donation Guard checks pending WooCommerce orders, GiveWP and DonasiAja donations against the payment gateway, marks the confirmed ones as paid, and flags anything unusual for a human.

* Dry-run by default
* Never changes a donation when the amount differs
* Audit log with CSV export
* Callback (webhook) URL health check
* WP-Cron, Run now button and WP-CLI
* Email and webhook reports

== External services ==

This plugin connects to the payment gateways you enable, only to read the status of existing payments:

* Midtrans (api.midtrans.com) - https://midtrans.com/terms-and-conditions
* Xendit (api.xendit.co) - https://www.xendit.co/en/terms-and-conditions/
* Tripay (tripay.co.id) - https://tripay.co.id/page/terms-and-conditions

The payment reference and your API key are sent. Nothing is sent until you enable a gateway.

== Changelog ==

= 0.2.0 =
DonasiAja support, callback health check, "no payment code" report.

= 0.1.0 =
First release.
