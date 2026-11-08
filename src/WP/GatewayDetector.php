<?php
namespace DonationGuard\WP;

/** Maps a plugin's payment method id (e.g. "midtrans_sub_gopay", "knit_pay_xendit") to our gateway key. */
final class GatewayDetector
{
    public static function detect(string $paymentMethod): string
    {
        $m = strtolower($paymentMethod);
        foreach (array_keys(Settings::GATEWAYS) as $g) {
            if (strpos($m, $g) !== false) {
                return (string) apply_filters('donation_guard_detect_gateway', $g, $paymentMethod);
            }
        }
        return (string) apply_filters('donation_guard_detect_gateway', '', $paymentMethod);
    }
}
