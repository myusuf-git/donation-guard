<?php
namespace DonationGuard\Tests\Support;

use DonationGuard\Core\Donation;
use DonationGuard\Sources\SourceInterface;

final class MemorySource implements SourceInterface
{
    /** @var Donation[] */ public $pending;
    /** @var array<string,string> id => paid|failed */ public $changes = [];

    public function __construct(array $pending) { $this->pending = $pending; }
    public function key(): string { return 'memory'; }
    public function isAvailable(): bool { return true; }
    public function findPending(int $olderThanMinutes, int $limit): array { return array_slice($this->pending, 0, $limit); }
    /** @var string[] ids whose update throws */ public $broken = [];
    public function markPaid(Donation $d, string $note): void { if (in_array($d->id, $this->broken, true)) { throw new \RuntimeException('db locked'); } $this->changes[$d->id] = 'paid'; }
    public function markFailed(Donation $d, string $note): void { $this->changes[$d->id] = 'failed'; }
}
