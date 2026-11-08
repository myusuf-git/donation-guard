<?php
namespace DonationGuard\WP;

use DonationGuard\Core\Outcome;
use DonationGuard\Core\Reconciler;
use DonationGuard\Gateways\Midtrans;
use DonationGuard\Gateways\Tripay;
use DonationGuard\Gateways\Xendit;
use DonationGuard\Http\WpClient;
use DonationGuard\Sources\GiveWP;
use DonationGuard\Sources\WooCommerce;

/** Wires settings + gateways + sources together and runs one reconciliation. */
final class Runner
{
    /** @return array summary saved as the "last report" */
    public static function run(string $triggeredBy, ?bool $forceDryRun = null): array
    {
        $s       = Settings::all();
        $dryRun  = $forceDryRun ?? (bool) $s['dry_run'];
        $runId   = gmdate('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 6);
        $rec     = new Reconciler((bool) $s['auto_fail']);
        $http    = new WpClient();
        $classes = ['midtrans' => Midtrans::class, 'xendit' => Xendit::class, 'tripay' => Tripay::class];
        foreach ($classes as $key => $class) {
            if (!empty($s['gw_' . $key . '_enabled']) && Settings::secret($key) !== '') {
                $base = apply_filters('donation_guard_gateway_base_url', null, $key);
                $rec->addGateway(new $class($http, Settings::secret($key), (bool) $s['gw_' . $key . '_sandbox'], $base));
            }
        }

        $summary = [
            'run_id' => $runId, 'started_at' => gmdate('c'), 'dry_run' => $dryRun,
            'triggered_by' => $triggeredBy, 'checked' => 0, 'counts' => [], 'attention' => 0, 'sources' => [],
        ];
        foreach ([new WooCommerce(), new GiveWP()] as $source) {
            if (empty($s['sources'][$source->key()]) || !$source->isAvailable()) {
                continue;
            }
            $report = $rec->run($source, (int) $s['older_than'], (int) $s['limit'], $dryRun);
            AuditLog::write($report, $source->key(), $runId, $triggeredBy);
            $summary['sources'][] = $source->key();
            $summary['checked']  += $report->checked();
            $summary['attention'] += count($report->needsAttention());
            foreach ($report->counts() as $action => $n) {
                $summary['counts'][$action] = ($summary['counts'][$action] ?? 0) + $n;
            }
        }
        update_option('donation_guard_last_report', $summary, false);
        Notifier::send($summary);
        do_action('donation_guard_after_run', $summary);
        return $summary;
    }

    public static function hasChanges(array $summary): bool
    {
        $c = $summary['counts'];
        return ($c[Outcome::MARKED_PAID] ?? 0) + ($c[Outcome::MARKED_FAILED] ?? 0)
            + ($c[Outcome::WOULD_PAY] ?? 0) + ($c[Outcome::WOULD_FAIL] ?? 0) + $summary['attention'] > 0;
    }
}
