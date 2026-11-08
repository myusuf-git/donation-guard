<?php
namespace DonationGuard\WP;

use DonationGuard\Core\Outcome;
use DonationGuard\Core\Reconciler;
use DonationGuard\Gateways\Midtrans;
use DonationGuard\Gateways\Tripay;
use DonationGuard\Gateways\Xendit;
use DonationGuard\Http\WpClient;
use DonationGuard\Core\CallbackProbe;
use DonationGuard\Sources\DonasiAja;
use DonationGuard\Sources\GiveWP;
use DonationGuard\Sources\WooCommerce;

/** Wires settings + gateways + sources together and runs one reconciliation. */
final class Runner
{
    public const FINAL_OPTION = 'donation_guard_final_ids';

    /** @return \DonationGuard\Sources\SourceInterface[] */
    public static function sources(): array
    {
        return [new WooCommerce(), new GiveWP(), new DonasiAja()];
    }

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
            'callbacks' => [],
        ];
        $final = get_option(self::FINAL_OPTION, []);
        $final = is_array($final) ? $final : [];
        foreach (self::sources() as $source) {
            if (empty($s['sources'][$source->key()]) || !$source->isAvailable()) {
                continue;
            }
            $skip   = array_keys($final[$source->key()] ?? []);
            $report = $rec->run($source, (int) $s['older_than'], (int) $s['limit'], $dryRun, null, $skip, (int) $s['lookback_days']);
            AuditLog::write($report, $source->key(), $runId, $triggeredBy);
            foreach ($report->outcomes as $o) {
                if ($o->action === Outcome::EXPIRED_KEPT) {
                    // Final at the gateway; don't ask again on every run.
                    $final[$source->key()][$o->donation->id] = time();
                }
            }
            $summary['sources'][] = $source->key();
            $summary['checked']  += $report->checked();
            $summary['attention'] += count($report->needsAttention());
            foreach ($report->counts() as $action => $n) {
                $summary['counts'][$action] = ($summary['counts'][$action] ?? 0) + $n;
            }
        }
        foreach ($final as $k => $ids) {
            $final[$k] = array_slice($ids, -5000, null, true);
        }
        update_option(self::FINAL_OPTION, $final, false);

        $probe = new CallbackProbe($http);
        foreach (Settings::callbackUrls() as [$gw, $url]) {
            $summary['callbacks'][] = $probe->probe($gw, $url);
        }
        foreach ($summary['callbacks'] as $c) {
            if ($c['state'] === CallbackProbe::FAIL) {
                $summary['attention']++;
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
            + ($c[Outcome::WOULD_PAY] ?? 0) + ($c[Outcome::WOULD_FAIL] ?? 0) + ($c[Outcome::NO_INVOICE] ?? 0)
            + $summary['attention'] > 0;
    }
}
