<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
global $wpdb;
delete_option('donation_guard_settings');
delete_option('donation_guard_last_report');
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}donation_guard_log"); // phpcs:ignore
