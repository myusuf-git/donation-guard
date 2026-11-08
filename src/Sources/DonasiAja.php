<?php
namespace DonationGuard\Sources;

use DonationGuard\Core\Donation;

/**
 * DonasiAja (popular Indonesian donation plugin, sold outside wordpress.org).
 *
 * Reads {prefix}dja_donate directly: status 0 = pending, 1 = paid. DonasiAja has no "failed"
 * status, so expired payments are only reported, never changed.
 *
 * Reference per gateway, matching how DonasiAja talks to each gateway:
 *  - midtrans: invoice_id  (sent as order_id)
 *  - tripay:   payment_trx_id (Tripay reference, T000...)
 *  - xendit:   invoice_id  (sent as external_id)
 *
 * Marking as paid goes through DonasiAja's own "set payment" routine so the donor gets the
 * same WhatsApp/email receipt and the Meta conversion event as with a manual confirmation.
 * The routine is resolved at runtime and can be replaced with the
 * `donation_guard_donasiaja_mark_paid` filter.
 */
final class DonasiAja implements SourceInterface
{
    public function key(): string { return 'donasiaja'; }

    public function isAvailable(): bool
    {
        global $wpdb;
        return function_exists('djafunction_set_payment')
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'dja_donate')) !== null;
    }

    public function canMarkFailed(): bool
    {
        return false;
    }

    public function findPending(int $olderThanMinutes, int $limit, int $lookbackDays = 7): array
    {
        global $wpdb;
        // DonasiAja stores local time of the site (it forces Asia/Jakarta for IDR).
        $tz    = new \DateTimeZone(wp_timezone_string() ?: 'Asia/Jakarta');
        $now   = new \DateTimeImmutable('now', $tz);
        $until = $now->modify('-' . $olderThanMinutes . ' minutes')->format('Y-m-d H:i:s');
        $from  = $now->modify('-' . $lookbackDays . ' days')->format('Y-m-d H:i:s');
        $rows  = $wpdb->get_results($wpdb->prepare(
            'SELECT id, invoice_id, payment_trx_id, payment_gateway, nominal, created_at FROM ' . $wpdb->prefix . 'dja_donate
             WHERE status = 0 AND payment_gateway IS NOT NULL AND payment_gateway <> \'\'
               AND created_at BETWEEN %s AND %s
             ORDER BY id ASC LIMIT %d',
            $from, $until, $limit
        ));
        $out = [];
        foreach ((array) $rows as $r) {
            $gateway = strtolower((string) $r->payment_gateway);
            $ref = $gateway === 'tripay' ? (string) $r->payment_trx_id : (string) $r->invoice_id;
            $d = new Donation((string) $r->id, $gateway, $ref, (float) $r->nominal, (string) $r->created_at);
            $out[] = apply_filters('donation_guard_donasiaja_donation', $d, $r);
        }
        return $out;
    }

    public function markPaid(Donation $d, string $note): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dja_donate';
        $handler = apply_filters('donation_guard_donasiaja_mark_paid', null, $d);
        if (is_callable($handler)) {
            $handler($d, $note);
        } else {
            $this->callNativeRoutine((int) $d->id);
        }
        // Record who did it, the same way DonasiAja records the gateway name.
        $wpdb->update($table, ['process_by' => 'donation-guard'], ['id' => (int) $d->id, 'status' => 1]);
        $status = $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . $table . ' WHERE id = %d', (int) $d->id));
        if ((string) $status !== '1') {
            throw new \RuntimeException('DonasiAja did not mark donation ' . $d->id . ' as paid');
        }
    }

    public function markFailed(Donation $d, string $note): void
    {
        // DonasiAja has no failed status; Reconciler never calls this (canMarkFailed() = false).
    }

    /**
     * DonasiAja's admin "confirm payment" routine reads $_POST['datanya'], echoes a result and
     * calls wp_die(). We run it in a sandbox: fake input, capture output, turn wp_die into an exception.
     */
    private function callNativeRoutine(int $id): void
    {
        global $wpdb;
        $still = $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . $wpdb->prefix . 'dja_donate WHERE id = %d', $id));
        if ((string) $still !== '0') {
            return; // paid meanwhile by a late callback: nothing to do
        }
        $oldPost = $_POST;
        $_POST['datanya'] = [(string) $id, '1'];
        $die = function () {
            return function () {
                throw new DonasiAjaDone();
            };
        };
        add_filter('wp_die_ajax_handler', $die, 999);
        add_filter('wp_die_handler', $die, 999);
        $level = ob_get_level();
        ob_start();
        try {
            \djafunction_set_payment();
        } catch (DonasiAjaDone $e) {
            // normal end of the routine
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            remove_filter('wp_die_ajax_handler', $die, 999);
            remove_filter('wp_die_handler', $die, 999);
            $_POST = $oldPost;
        }
    }
}
