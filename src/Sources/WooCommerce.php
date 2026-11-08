<?php
namespace DonationGuard\Sources;

use DonationGuard\Core\Donation;
use DonationGuard\WP\GatewayDetector;
use DonationGuard\WP\Settings;

/**
 * WooCommerce orders with status "pending" (Pending payment).
 * Reference: the order meta key configured per gateway, falling back to the order number.
 */
final class WooCommerce implements SourceInterface
{
    public function key(): string { return 'woocommerce'; }

    public function isAvailable(): bool
    {
        return function_exists('wc_get_orders');
    }

    public function findPending(int $olderThanMinutes, int $limit): array
    {
        $orders = wc_get_orders([
            'status'       => ['pending'],
            'date_created' => '<' . (time() - $olderThanMinutes * 60),
            'limit'        => $limit,
            'orderby'      => 'date',
            'order'        => 'ASC',
        ]);
        $out = [];
        foreach ($orders as $order) {
            $gateway = GatewayDetector::detect((string) $order->get_payment_method());
            $metaKey = $gateway ? (string) Settings::get('gw_' . $gateway . '_ref_meta') : '';
            $ref     = $metaKey !== '' ? (string) $order->get_meta($metaKey) : (string) $order->get_order_number();
            $created = $order->get_date_created();
            $d = new Donation(
                (string) $order->get_id(),
                $gateway,
                $ref,
                (float) $order->get_total(),
                $created ? $created->date('c') : ''
            );
            $out[] = apply_filters('donation_guard_woocommerce_donation', $d, $order);
        }
        return $out;
    }

    public function markPaid(Donation $d, string $note): void
    {
        $order = wc_get_order((int) $d->id);
        if ($order && $order->has_status('pending')) {
            $order->add_order_note($note);
            $order->payment_complete($d->reference);
        }
    }

    public function markFailed(Donation $d, string $note): void
    {
        $order = wc_get_order((int) $d->id);
        if ($order && $order->has_status('pending')) {
            $order->update_status('failed', $note);
        }
    }
}
