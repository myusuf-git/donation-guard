<?php
namespace DonationGuard\WP;

use DonationGuard\Core\Report;

/** Every decision is written to {prefix}donation_guard_log. */
final class AuditLog
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'donation_guard_log';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            run_id VARCHAR(40) NOT NULL,
            source VARCHAR(30) NOT NULL,
            donation_id VARCHAR(40) NOT NULL,
            gateway VARCHAR(30) NOT NULL,
            reference VARCHAR(190) NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            action VARCHAR(30) NOT NULL,
            gateway_status VARCHAR(60) NOT NULL DEFAULT '',
            dry_run TINYINT(1) NOT NULL DEFAULT 1,
            triggered_by VARCHAR(60) NOT NULL DEFAULT '',
            note TEXT NULL,
            PRIMARY KEY  (id),
            KEY donation (source, donation_id),
            KEY run (run_id)
        ) $charset;");
    }

    public static function write(Report $r, string $source, string $runId, string $by): void
    {
        global $wpdb;
        foreach ($r->outcomes as $o) {
            $wpdb->insert(self::table(), [
                'created_at'     => current_time('mysql', true),
                'run_id'         => $runId,
                'source'         => $source,
                'donation_id'    => $o->donation->id,
                'gateway'        => $o->donation->gateway,
                'reference'      => substr($o->donation->reference, 0, 190),
                'amount'         => $o->donation->amount,
                'action'         => $o->action,
                'gateway_status' => $o->status ? substr($o->status->raw, 0, 60) : '',
                'dry_run'        => $r->dryRun ? 1 : 0,
                'triggered_by'   => $by,
                'note'           => $o->note,
            ]);
        }
    }

    public static function recent(int $limit = 100): array
    {
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit), ARRAY_A);
    }

    public static function forRun(string $runId): array
    {
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE run_id = %s ORDER BY id ASC', $runId), ARRAY_A);
    }
}
