<?php
namespace DonationGuard\WP;

/** Tools > Donation Guard. */
final class Admin
{
    private const SLUG = 'donation-guard';

    public static function register(): void
    {
        add_action('admin_menu', function () {
            add_management_page('Donation Guard', 'Donation Guard', 'manage_options', self::SLUG, [self::class, 'render']);
        });
        add_action('admin_init', function () {
            register_setting('donation_guard', Settings::OPTION, ['sanitize_callback' => [Settings::class, 'sanitize']]);
        });
        add_action('admin_post_donation_guard_run', [self::class, 'handleRun']);
        add_action('admin_post_donation_guard_csv', [self::class, 'handleCsv']);
        add_filter('plugin_action_links_' . plugin_basename(DONATION_GUARD_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(admin_url('tools.php?page=' . self::SLUG)) . '">Settings</a>');
            return $links;
        });
    }

    public static function handleRun(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('donation_guard_run');
        $mode  = sanitize_key($_POST['mode'] ?? 'settings');
        $force = $mode === 'dry' ? true : ($mode === 'live' ? false : null);
        $user  = wp_get_current_user();
        Runner::run('admin:' . $user->user_login, $force);
        wp_safe_redirect(admin_url('tools.php?page=' . self::SLUG . '&ran=1'));
        exit;
    }

    public static function handleCsv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('donation_guard_csv');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=donation-guard-log-' . gmdate('Ymd-His') . '.csv');
        $out  = fopen('php://output', 'w');
        $rows = AuditLog::recent(5000);
        if ($rows) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) {
                // Neutralise spreadsheet formula injection.
                fputcsv($out, array_map(function ($v) {
                    return is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
                }, $r));
            }
        }
        fclose($out);
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tab = sanitize_key($_GET['tab'] ?? 'dashboard');
        $tabs = ['dashboard' => 'Dashboard', 'settings' => 'Settings', 'log' => 'Log'];
        echo '<div class="wrap"><h1>Donation Guard</h1><nav class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url(admin_url('tools.php?page=' . self::SLUG . '&tab=' . $k)), $tab === $k ? ' nav-tab-active' : '', esc_html($label));
        }
        echo '</nav>';
        if ($tab === 'settings') {
            self::settings();
        } elseif ($tab === 'log') {
            self::log();
        } else {
            self::dashboard();
        }
        echo '</div>';
    }

    private static function dashboard(): void
    {
        $s = Settings::all();
        if (!empty($_GET['ran'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Reconciliation finished.</p></div>';
        }
        if ($s['dry_run']) {
            echo '<div class="notice notice-info"><p><strong>Dry-run is on.</strong> Scheduled and default runs only report; they do not change any donation. ("Run and apply changes" and <code>wp donation-guard run --live</code> override this.) Turn it off in Settings when the report looks right.</p></div>';
        }
        $enabled = array_filter(array_keys(Settings::GATEWAYS), function ($g) use ($s) {
            return !empty($s['gw_' . $g . '_enabled']) && Settings::secret($g) !== '';
        });
        if (!$enabled) {
            echo '<div class="notice notice-warning"><p>No gateway configured yet. Add at least one API key in Settings.</p></div>';
        }
        $sources = [];
        foreach (Runner::sources() as $src) {
            $sources[] = $src->key() . ': ' . ($src->isAvailable() ? (empty($s['sources'][$src->key()]) ? 'disabled' : 'active') : 'not installed');
        }
        echo '<p>Sources: ' . self::badges($sources) . '</p>';
        echo '<p>Gateways: ' . self::badges($enabled ? array_values($enabled) : ['none']) . ' &middot; Schedule: <code>' . esc_html($s['schedule']) . '</code> &middot; Checks donations pending longer than <code>' . (int) $s['older_than'] . ' min</code></p>';

        $last = get_option('donation_guard_last_report');
        echo '<h2>Last run</h2>';
        if (!$last) {
            echo '<p>No run yet.</p>';
        } else {
            $ts = strtotime($last['started_at']);
            printf('<p>%s (%s ago) &middot; by %s &middot; %s</p>',
                esc_html(wp_date('j M Y H:i T', $ts)), esc_html(human_time_diff($ts)), esc_html($last['triggered_by']),
                $last['dry_run'] ? '<strong>dry-run</strong> (nothing changed)' : '<strong>live</strong> (changes applied)');
            echo '<table class="widefat striped" style="max-width:520px"><tbody>';
            printf('<tr><th><strong>Need a human</strong> <span class="description">(amount mismatch, unresolved, failing callback URL)</span></th><td><strong>%d</strong></td></tr>', (int) $last['attention']);
            printf('<tr><th><strong>Donations checked</strong></th><td><strong>%d</strong></td></tr>', (int) $last['checked']);
            foreach ($last['counts'] as $k => $n) {
                printf('<tr><th style="padding-left:24px">%s</th><td>%d</td></tr>', esc_html(self::label($k)), (int) $n);
            }
            echo '</tbody></table>';
            if (!empty($last['callbacks'])) {
                echo '<h3>Callback URLs</h3><table class="widefat striped" style="max-width:900px"><thead><tr><th>Gateway</th><th>URL</th><th>State</th><th>HTTP</th><th>Time</th><th>Message</th></tr></thead><tbody>';
                foreach ($last['callbacks'] as $c) {
                    $color = $c['state'] === 'ok' ? '#008a20' : ($c['state'] === 'warning' ? '#996800' : '#d63638');
                    printf('<tr><td>%s</td><td><code>%s</code></td><td style="color:%s;font-weight:600">%s</td><td>%s</td><td>%ss</td><td>%s</td></tr>',
                        esc_html($c['gateway']), esc_html($c['url']), esc_attr($color), esc_html($c['state']), esc_html((string) $c['http']), esc_html((string) $c['seconds']), esc_html($c['message']));
                }
                echo '</tbody></table>';
            }
            $rows = AuditLog::forRun($last['run_id']);
            if ($rows) {
                echo '<h3>Details</h3>';
                self::table($rows);
            }
        }

        echo '<h2>Run now</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('donation_guard_run');
        echo '<input type="hidden" name="action" value="donation_guard_run">';
        echo '<button class="button" name="mode" value="dry">Run dry-run</button> ';
        echo '<button class="button button-primary" name="mode" value="live" onclick="return confirm(\'Apply changes to donations now?\')">Run and apply changes</button>';
        echo '</form>';
    }

    private static function settings(): void
    {
        $s = Settings::all();
        $n = Settings::OPTION;
        echo '<form method="post" action="options.php">';
        settings_fields('donation_guard');
        echo '<h2>Behaviour</h2><table class="form-table">';
        self::checkbox($n . '[dry_run]', $s['dry_run'], 'Dry-run (report only, never change donations)');
        self::checkbox($n . '[auto_fail]', $s['auto_fail'], 'Mark donations as failed when the gateway says expired / failed');
        printf('<tr><th>Check donations pending longer than</th><td><input type="number" min="5" name="%s[older_than]" value="%d" class="small-text"> minutes</td></tr>', esc_attr($n), (int) $s['older_than']);
        printf('<tr><th>Max donations per run</th><td><input type="number" min="1" max="500" name="%s[limit]" value="%d" class="small-text"></td></tr>', esc_attr($n), (int) $s['limit']);
        echo '<tr><th>Schedule</th><td><select name="' . esc_attr($n) . '[schedule]">';
        foreach (['hourly' => 'Hourly', 'twicedaily' => 'Twice daily', 'daily' => 'Daily', 'off' => 'Off (manual / WP-CLI only)'] as $k => $l) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['schedule'], $k, false), esc_html($l));
        }
        echo '</select></td></tr>';
        echo '<tr><th>Donation plugins</th><td>';
        printf('<label><input type="checkbox" name="%s[sources][woocommerce]" value="1"%s> WooCommerce</label><br>', esc_attr($n), checked($s['sources']['woocommerce'] ?? 0, 1, false));
        printf('<label><input type="checkbox" name="%s[sources][givewp]" value="1"%s> GiveWP</label><br>', esc_attr($n), checked($s['sources']['givewp'] ?? 0, 1, false));
        printf('<label><input type="checkbox" name="%s[sources][donasiaja]" value="1"%s> DonasiAja</label>', esc_attr($n), checked($s['sources']['donasiaja'] ?? 0, 1, false));
        echo '<p class="description">DonasiAja has no "failed" status: expired payments are reported, not changed. Paid ones are confirmed through DonasiAja itself, so donors get the usual receipt.</p>';
        echo '</td></tr>';
        printf('<tr><th>Look back</th><td><input type="number" min="1" max="90" name="%s[lookback_days]" value="%d" class="small-text"> days</td></tr>', esc_attr($n), (int) $s['lookback_days']);
        echo '</table>';

        echo '<h2>Callback health check</h2><p>One line per callback (webhook) URL: <code>gateway | URL</code>. Each run sends a harmless unsigned test request and reports timeouts, server errors, redirects, firewall blocks and non-JSON answers.</p>';
        printf('<textarea name="%s[callback_urls]" rows="6" class="large-text code" placeholder="tripay | https://example.org/callback_tripay/">%s</textarea>', esc_attr($n), esc_textarea($s['callback_urls']));

        echo '<h2>Gateways</h2><p>Keys are stored in the database and never shown again. For better security define them in <code>wp-config.php</code>, e.g. <code>define(\'DONATION_GUARD_MIDTRANS_SECRET\', \'...\');</code></p>';
        $help = [
            'midtrans' => 'Server key. Reference = order_id sent to Midtrans.',
            'xendit'   => 'Secret API key (read access to Invoices). Reference = external_id.',
            'tripay'   => 'API key. Reference = Tripay reference (T000...), usually stored in an order meta field.',
        ];
        foreach (Settings::GATEWAYS as $g => $label) {
            echo '<h3>' . esc_html($label) . '</h3><table class="form-table">';
            self::checkbox($n . '[gw_' . $g . '_enabled]', $s['gw_' . $g . '_enabled'], 'Enabled');
            self::checkbox($n . '[gw_' . $g . '_sandbox]', $s['gw_' . $g . '_sandbox'], 'Sandbox mode');
            if (Settings::secretFromConstant($g)) {
                echo '<tr><th>Key</th><td>Set in wp-config.php</td></tr>';
            } else {
                $has = $s['gw_' . $g . '_secret'] !== '';
                printf('<tr><th>Key</th><td><input type="password" autocomplete="off" name="%s[gw_%s_secret]" class="regular-text" placeholder="%s"> %s<p class="description">%s</p></td></tr>',
                    esc_attr($n), esc_attr($g), $has ? 'Saved - leave empty to keep' : 'Not set',
                    $has ? '<label><input type="checkbox" name="' . esc_attr($n) . '[gw_' . esc_attr($g) . '_clear]" value="1"> remove</label>' : '',
                    esc_html($help[$g]));
            }
            printf('<tr><th>Reference meta key</th><td><input type="text" name="%s[gw_%s_ref_meta]" value="%s" class="regular-text" placeholder="optional"><p class="description">Order/donation meta field holding the gateway reference. Empty = use the order number.</p></td></tr>',
                esc_attr($n), esc_attr($g), esc_attr($s['gw_' . $g . '_ref_meta']));
            echo '</table>';
        }

        echo '<h2>Notifications</h2><table class="form-table">';
        printf('<tr><th>Email</th><td><input type="email" name="%s[report_email]" value="%s" class="regular-text"></td></tr>', esc_attr($n), esc_attr($s['report_email']));
        printf('<tr><th>Webhook URL</th><td><input type="url" name="%s[webhook_url]" value="%s" class="regular-text"><p class="description">Receives JSON <code>{text, summary, site}</code>. Works with Slack, n8n, Telegram/WhatsApp bridges.</p></td></tr>', esc_attr($n), esc_attr($s['webhook_url']));
        echo '<tr><th>Send</th><td><select name="' . esc_attr($n) . '[notify]">';
        foreach (['changes' => 'Only when something changed or needs attention', 'always' => 'After every run', 'never' => 'Never'] as $k => $l) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['notify'], $k, false), esc_html($l));
        }
        echo '</select></td></tr></table>';
        submit_button();
        echo '</form>';
    }

    private static function log(): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0">';
        wp_nonce_field('donation_guard_csv');
        echo '<input type="hidden" name="action" value="donation_guard_csv"><button class="button">Export CSV</button></form>';
        self::table(AuditLog::recent(200), true);
    }

    private static function table(array $rows, bool $withTime = false): void
    {
        $cols = ['source', 'donation_id', 'gateway', 'reference', 'amount', 'action', 'gateway_status', 'note'];
        if ($withTime) {
            array_unshift($cols, 'created_at');
            $cols[] = 'dry_run';
            $cols[] = 'triggered_by';
        }
        echo '<table class="widefat striped"><thead><tr>';
        foreach ($cols as $c) {
            echo '<th>' . esc_html($c) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="' . count($cols) . '">Nothing logged yet.</td></tr>';
        }
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($cols as $c) {
                $v = $c === 'action' ? self::label((string) $r[$c]) : (string) $r[$c];
                echo '<td>' . esc_html($v) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public static function label(string $action): string
    {
        $map = [
            'marked_paid' => 'Marked as paid', 'marked_failed' => 'Marked as failed',
            'would_mark_paid' => 'Would mark as paid', 'would_mark_failed' => 'Would mark as failed',
            'still_pending' => 'Still pending at gateway', 'amount_mismatch' => 'Amount mismatch',
            'unresolved' => 'Unresolved', 'skipped' => 'Skipped (unsupported gateway)',
            'no_payment_code' => 'No payment code issued', 'expired_left_pending' => 'Not paid (expired), left as is',
        ];
        return $map[$action] ?? $action;
    }

    private static function badges(array $items): string
    {
        return implode(' ', array_map(function ($i) {
            return '<code>' . esc_html((string) $i) . '</code>';
        }, $items));
    }

    private static function checkbox(string $name, $value, string $label): void
    {
        printf('<tr><th></th><td><label><input type="checkbox" name="%s" value="1"%s> %s</label></td></tr>', esc_attr($name), checked((int) $value, 1, false), esc_html($label));
    }
}
