<?php
namespace DonationGuard\Sources;

use DonationGuard\Core\Donation;

/** A donation plugin (WooCommerce, GiveWP, ...). */
interface SourceInterface
{
    public function key(): string;

    /** Is the plugin installed and active? */
    public function isAvailable(): bool;

    /**
     * @return Donation[] pending donations created between $lookbackDays ago and $olderThanMinutes ago,
     *                    oldest first
     */
    public function findPending(int $olderThanMinutes, int $limit, int $lookbackDays = 7): array;

    public function markPaid(Donation $d, string $note): void;

    public function markFailed(Donation $d, string $note): void;

    /** False for plugins that only know "pending" and "paid" (e.g. DonasiAja). */
    public function canMarkFailed(): bool;
}
