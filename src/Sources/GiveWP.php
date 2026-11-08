<?php
namespace DonationGuard\Sources;

use DonationGuard\Core\Donation;
use DonationGuard\WP\GatewayDetector;
use DonationGuard\WP\Settings;

/**
 * GiveWP donations (post type give_payment) with status "pending".
 * Reference: configured meta key, then _give_payment_transaction_id, then the donation id.
 */
final class GiveWP implements SourceInterface
{
    public function key(): string { return 'givewp'; }

    public function isAvailable(): bool
    {
        return function_exists('give_update_payment_status');
    }

    public function findPending(int $olderThanMinutes, int $limit): array
    {
        $posts = get_posts([
            'post_type'      => 'give_payment',
            'post_status'    => 'pending',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'date_query'     => [['before' => gmdate('Y-m-d H:i:s', time() - $olderThanMinutes * 60), 'column' => 'post_date_gmt']],
            'fields'         => 'ids',
        ]);
        $out = [];
        foreach ($posts as $id) {
            $gateway = GatewayDetector::detect((string) get_post_meta($id, '_give_payment_gateway', true));
            $metaKey = $gateway ? (string) Settings::get('gw_' . $gateway . '_ref_meta') : '';
            $ref = $metaKey !== '' ? (string) get_post_meta($id, $metaKey, true) : '';
            if ($ref === '') {
                $ref = (string) get_post_meta($id, '_give_payment_transaction_id', true);
            }
            if ($ref === '') {
                $ref = (string) $id;
            }
            $d = new Donation(
                (string) $id,
                $gateway,
                $ref,
                (float) get_post_meta($id, '_give_payment_total', true),
                (string) get_post_field('post_date_gmt', $id)
            );
            $out[] = apply_filters('donation_guard_givewp_donation', $d, $id);
        }
        return $out;
    }

    public function markPaid(Donation $d, string $note): void
    {
        if (get_post_status((int) $d->id) === 'pending') {
            give_update_payment_status((int) $d->id, 'publish');
            give_insert_payment_note((int) $d->id, $note);
        }
    }

    public function markFailed(Donation $d, string $note): void
    {
        if (get_post_status((int) $d->id) === 'pending') {
            give_update_payment_status((int) $d->id, 'failed');
            give_insert_payment_note((int) $d->id, $note);
        }
    }
}
