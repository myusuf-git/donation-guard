<?php
namespace DonationGuard\WP;

/** Plugin settings with safe defaults. Secrets can be set as constants in wp-config.php instead. */
final class Settings
{
    public const OPTION = 'donation_guard_settings';
    public const GATEWAYS = ['midtrans' => 'Midtrans', 'xendit' => 'Xendit', 'tripay' => 'Tripay'];

    public static function defaults(): array
    {
        $d = [
            'dry_run'        => 1,
            'older_than'     => 30,
            'limit'          => 50,
            'auto_fail'      => 1,
            'schedule'       => 'hourly',
            'sources'        => ['woocommerce' => 1, 'givewp' => 1, 'donasiaja' => 1],
            'lookback_days'  => 7,
            'callback_urls'  => '',
            'report_email'   => '',
            'webhook_url'    => '',
            'notify'         => 'changes', // changes | always | never
        ];
        foreach (array_keys(self::GATEWAYS) as $g) {
            $d['gw_' . $g . '_enabled'] = 0;
            $d['gw_' . $g . '_sandbox'] = 1;
            $d['gw_' . $g . '_secret']  = '';
            $d['gw_' . $g . '_ref_meta'] = '';
        }
        return $d;
    }

    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_replace(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function get(string $key)
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    /** Secret from constant (DONATION_GUARD_MIDTRANS_SECRET) wins over the database. */
    public static function secret(string $gateway): string
    {
        $const = 'DONATION_GUARD_' . strtoupper($gateway) . '_SECRET';
        if (defined($const)) {
            return (string) constant($const);
        }
        return (string) self::get('gw_' . $gateway . '_secret');
    }

    /** @return array<int,array{0:string,1:string}> [gateway, url] */
    public static function callbackUrls(): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) self::get('callback_urls')) as $line) {
            $p = array_map('trim', explode('|', $line, 2));
            if (count($p) === 2 && $p[1] !== '') {
                $out[] = [$p[0], $p[1]];
            }
        }
        return $out;
    }

    public static function secretFromConstant(string $gateway): bool
    {
        return defined('DONATION_GUARD_' . strtoupper($gateway) . '_SECRET');
    }

    public static function sanitize($in): array
    {
        $in  = is_array($in) ? $in : [];
        $old = self::all();
        $out = self::defaults();
        $out['dry_run']    = empty($in['dry_run']) ? 0 : 1;
        $out['auto_fail']  = empty($in['auto_fail']) ? 0 : 1;
        $out['older_than'] = max(5, min(10080, (int) ($in['older_than'] ?? 30)));
        $out['limit']      = max(1, min(500, (int) ($in['limit'] ?? 50)));
        $out['schedule']   = in_array($in['schedule'] ?? '', ['hourly', 'twicedaily', 'daily', 'off'], true) ? $in['schedule'] : 'hourly';
        $out['notify']     = in_array($in['notify'] ?? '', ['changes', 'always', 'never'], true) ? $in['notify'] : 'changes';
        $out['sources']    = [
            'woocommerce' => empty($in['sources']['woocommerce']) ? 0 : 1,
            'givewp'      => empty($in['sources']['givewp']) ? 0 : 1,
            'donasiaja'   => empty($in['sources']['donasiaja']) ? 0 : 1,
        ];
        $out['lookback_days'] = max(1, min(90, (int) ($in['lookback_days'] ?? 7)));
        $urls = [];
        foreach (preg_split('/\R/', (string) ($in['callback_urls'] ?? '')) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) === 2 && isset(self::GATEWAYS[strtolower($parts[0])]) && wp_http_validate_url($parts[1])) {
                $urls[] = strtolower($parts[0]) . ' | ' . esc_url_raw($parts[1]);
            }
        }
        $out['callback_urls'] = implode("\n", $urls);
        $out['report_email'] = sanitize_email($in['report_email'] ?? '');
        $out['webhook_url']  = esc_url_raw($in['webhook_url'] ?? '');
        foreach (array_keys(self::GATEWAYS) as $g) {
            $out['gw_' . $g . '_enabled']  = empty($in['gw_' . $g . '_enabled']) ? 0 : 1;
            $out['gw_' . $g . '_sandbox']  = empty($in['gw_' . $g . '_sandbox']) ? 0 : 1;
            $out['gw_' . $g . '_ref_meta'] = sanitize_key($in['gw_' . $g . '_ref_meta'] ?? '');
            $secret = trim((string) ($in['gw_' . $g . '_secret'] ?? ''));
            // Empty field = keep the stored secret (we never print it back into the form).
            $out['gw_' . $g . '_secret'] = $secret === '' ? (string) $old['gw_' . $g . '_secret'] : sanitize_text_field($secret);
            if (!empty($in['gw_' . $g . '_clear'])) {
                $out['gw_' . $g . '_secret'] = '';
            }
        }
        Plugin::reschedule($out['schedule']);
        return $out;
    }
}
