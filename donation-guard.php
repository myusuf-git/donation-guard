<?php
/**
 * Plugin Name:       Donation Guard
 * Plugin URI:        https://github.com/myusuf-git/donation-guard
 * Description:       Finds donations stuck in "pending" and checks their real status with Midtrans, Xendit and Tripay. Dry-run by default.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Donation Guard contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       donation-guard
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DONATION_GUARD_VERSION', '0.1.0');
define('DONATION_GUARD_FILE', __FILE__);
define('DONATION_GUARD_DIR', __DIR__);

spl_autoload_register(function ($class) {
    $prefix = 'DonationGuard\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, ['DonationGuard\\WP\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['DonationGuard\\WP\\Plugin', 'deactivate']);

add_action('plugins_loaded', ['DonationGuard\\WP\\Plugin', 'boot']);

// Works with WooCommerce High-Performance Order Storage (uses the wc_get_orders / WC_Order API only).
add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
