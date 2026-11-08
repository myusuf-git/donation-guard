<?php
namespace DonationGuard\Sources;

use DonationGuard\Core\Donation;

/** A donation plugin (WooCommerce, GiveWP, ...). */
interface SourceInterface
{
    public function key(): string;

    /** Is the plugin installed and active? */
    public function isAvailable(): bool;

    /** @return Donation[] pending donations older than N minutes, oldest first */
    public function findPending(int $olderThanMinutes, int $limit): array;

    public function markPaid(Donation $d, string $note): void;

    public function markFailed(Donation $d, string $note): void;
}
