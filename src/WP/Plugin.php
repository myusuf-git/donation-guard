<?php
namespace DonationGuard\WP;

final class Plugin
{
    public const CRON_HOOK = 'donation_guard_run';

    public static function boot(): void
    {
        add_action(self::CRON_HOOK, function () {
            Runner::run('cron');
        });
        if (is_admin()) {
            Admin::register();
        }
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('donation-guard', Cli::class);
        }
    }

    public static function activate(): void
    {
        AuditLog::install();
        if (get_option(Settings::OPTION) === false) {
            add_option(Settings::OPTION, Settings::defaults(), '', false);
        }
        self::reschedule((string) Settings::get('schedule'));
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function reschedule(string $schedule): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        if ($schedule !== 'off') {
            wp_schedule_event(time() + 300, $schedule, self::CRON_HOOK);
        }
    }
}
