<?php
namespace DonationGuard\Tests\Support;

use DonationGuard\Core\Donation;
use DonationGuard\Sources\SourceInterface;

final class MemorySource implements SourceInterface
{
    /** @var Donation[] */ public $pending;
    /** @var array<string,string> id => paid|failed */ public $changes = [];
    /** @var string[] ids whose update throws */ public $broken = [];
    /** @var bool */ public $canFail = true;

    public function __construct(array $pending) { $this->pending = $pending; }
    public function key(): string { return 'memory'; }
    public function isAvailable(): bool { return true; }
    public function canMarkFailed(): bool { return $this->canFail; }
    public function findPending(int $olderThanMinutes, int $limit, int $lookbackDays = 7): array { return array_slice($this->pending, 0, $limit); }
    public function markPaid(Donation $d, string $note): void
    {
        if (in_array($d->id, $this->broken, true)) {
            throw new \RuntimeException('db locked');
        }
        $this->changes[$d->id] = 'paid';
    }
    public function markFailed(Donation $d, string $note): void { $this->changes[$d->id] = 'failed'; }
}
