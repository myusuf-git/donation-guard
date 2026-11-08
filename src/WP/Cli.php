<?php
namespace DonationGuard\WP;

/**
 * Reconcile pending donations from the command line.
 *
 * ## EXAMPLES
 *
 *     wp donation-guard run --dry-run
 *     wp donation-guard run --live
 *     wp donation-guard log --limit=20
 */
final class Cli
{
    /**
     * Run a reconciliation now.
     *
     * [--dry-run]
     * : Report only, change nothing (overrides settings).
     *
     * [--live]
     * : Apply changes (overrides settings).
     *
     * [--format=<format>]
     * : table or json. Default: table.
     */
    public function run($args, $assoc): void
    {
        $force = null;
        if (!empty($assoc['dry-run'])) {
            $force = true;
        } elseif (!empty($assoc['live'])) {
            $force = false;
        }
        $summary = Runner::run('wp-cli', $force);
        if (($assoc['format'] ?? 'table') === 'json') {
            \WP_CLI::line((string) wp_json_encode($summary, JSON_PRETTY_PRINT));
            return;
        }
        $rows = array_map(function ($r) {
            return array_intersect_key($r, array_flip(['source', 'donation_id', 'gateway', 'reference', 'amount', 'action', 'gateway_status', 'note']));
        }, AuditLog::forRun($summary['run_id']));
        if ($rows) {
            \WP_CLI\Utils\format_items('table', $rows, ['source', 'donation_id', 'gateway', 'reference', 'amount', 'action', 'gateway_status', 'note']);
        }
        \WP_CLI::success(Notifier::headline($summary));
    }

    /**
     * Show recent log entries.
     *
     * [--limit=<n>]
     * : Default 20.
     */
    public function log($args, $assoc): void
    {
        $rows = AuditLog::recent((int) ($assoc['limit'] ?? 20));
        \WP_CLI\Utils\format_items('table', $rows, ['created_at', 'source', 'donation_id', 'gateway', 'action', 'gateway_status', 'dry_run']);
    }
}
