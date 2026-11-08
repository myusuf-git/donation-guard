<?php
namespace DonationGuard\WP;

/** Sends the run summary by email and/or JSON webhook (Slack, Telegram bots, n8n, WhatsApp gateways ...). */
final class Notifier
{
    public static function send(array $summary): void
    {
        $s = Settings::all();
        if ($s['notify'] === 'never' || ($s['notify'] === 'changes' && !Runner::hasChanges($summary))) {
            return;
        }
        $text = self::text($summary);
        if ($s['report_email'] !== '') {
            wp_mail($s['report_email'], '[Donation Guard] ' . self::headline($summary), $text);
        }
        if ($s['webhook_url'] !== '') {
            wp_remote_post($s['webhook_url'], [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['text' => $text, 'summary' => $summary, 'site' => home_url()]),
            ]);
        }
    }

    public static function headline(array $s): string
    {
        return sprintf('%d checked, %d need attention%s', $s['checked'], $s['attention'], $s['dry_run'] ? ' (dry-run)' : '');
    }

    public static function text(array $s): string
    {
        $lines = ['Donation Guard report for ' . home_url(), self::headline($s), ''];
        foreach ($s['counts'] as $action => $n) {
            $lines[] = sprintf('- %s: %d', $action, $n);
        }
        $lines[] = '';
        $lines[] = 'Details: ' . admin_url('tools.php?page=donation-guard');
        return implode("\n", $lines);
    }
}
