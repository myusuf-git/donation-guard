<?php
namespace DonationGuard\Tests\Unit;

use DonationGuard\Core\Donation;
use DonationGuard\Core\Outcome;
use DonationGuard\Core\Reconciler;
use DonationGuard\Gateways\Midtrans;
use DonationGuard\Gateways\Tripay;
use DonationGuard\Tests\Support\FakeHttp;
use DonationGuard\Tests\Support\MemorySource;
use PHPUnit\Framework\TestCase;

final class ReconcilerTest extends TestCase
{
    private function reconciler(bool $autoFail = true): Reconciler
    {
        $mid = new FakeHttp([
            '/PAID-OK/'    => [200, 'midtrans/settlement.json'],   // 50000
            '/PENDING/'    => [200, 'midtrans/pending.json'],
            '/EXPIRED/'    => [200, 'midtrans/expire.json'],
            '/NOPE/'       => [200, 'midtrans/not_found.json'],
            '/DOWN/'       => [0, 'THROW'],
        ]);
        $tri = new FakeHttp(['reference=T-REFUND' => [200, 'tripay/refund.json']]);
        return (new Reconciler($autoFail))
            ->addGateway(new Midtrans($mid, 'k', true))
            ->addGateway(new Tripay($tri, 'k', true));
    }

    private function source(): MemorySource
    {
        return new MemorySource([
            new Donation('1', 'midtrans', 'PAID-OK', 50000),
            new Donation('2', 'midtrans', 'PAID-OK', 45000),   // amount mismatch
            new Donation('3', 'midtrans', 'PENDING', 75000),
            new Donation('4', 'midtrans', 'EXPIRED', 20000),
            new Donation('5', 'midtrans', 'NOPE', 10000),
            new Donation('6', 'midtrans', 'DOWN', 10000),
            new Donation('7', 'tripay', 'T-REFUND', 30000),
            new Donation('8', 'paypal', 'X', 10000),
            new Donation('9', 'midtrans', '', 10000),
        ]);
    }

    private function actions($report): array
    {
        $a = [];
        foreach ($report->outcomes as $o) { $a[$o->donation->id] = $o->action; }
        return $a;
    }

    public function testLiveRunAppliesOnlySafeChanges(): void
    {
        $src = $this->source();
        $r = $this->reconciler()->run($src, 30, 100, false);
        $this->assertSame([
            '1' => Outcome::MARKED_PAID,
            '2' => Outcome::MISMATCH,
            '3' => Outcome::STILL_PENDING,
            '4' => Outcome::MARKED_FAILED,
            '5' => Outcome::UNRESOLVED,
            '6' => Outcome::UNRESOLVED,
            '7' => Outcome::UNRESOLVED,
            '8' => Outcome::SKIPPED,
            '9' => Outcome::NO_INVOICE,
        ], $this->actions($r));
        $this->assertSame(['1' => 'paid', '4' => 'failed'], $src->changes);
        $this->assertCount(4, $r->needsAttention());
        $this->assertSame(9, $r->checked());
    }

    public function testDryRunChangesNothing(): void
    {
        $src = $this->source();
        $r = $this->reconciler()->run($src, 30, 100, true);
        $this->assertSame([], $src->changes);
        $this->assertSame(Outcome::WOULD_PAY, $this->actions($r)['1']);
        $this->assertSame(Outcome::WOULD_FAIL, $this->actions($r)['4']);
        $this->assertTrue($r->toArray()['dry_run']);
    }

    public function testAutoFailCanBeDisabled(): void
    {
        $src = $this->source();
        $r = $this->reconciler(false)->run($src, 30, 100, false);
        $this->assertSame(Outcome::STILL_PENDING, $this->actions($r)['4']);
        $this->assertSame(['1' => 'paid'], $src->changes);
    }

    public function testUpdateErrorDoesNotStopTheRun(): void
    {
        $src = $this->source();
        $src->broken = ['1'];
        $r = $this->reconciler()->run($src, 30, 100, false);
        $this->assertSame(Outcome::UNRESOLVED, $this->actions($r)['1']);
        $this->assertStringContainsString('db locked', $r->outcomes[0]->note);
        $this->assertSame(['4' => 'failed'], $src->changes);
        $this->assertSame(9, $r->checked());
    }

    public function testSourceWithoutFailedStatusOnlyReportsExpired(): void
    {
        $src = $this->source();
        $src->canFail = false;
        $r = $this->reconciler()->run($src, 30, 100, false);
        $this->assertSame(Outcome::EXPIRED_KEPT, $this->actions($r)['4']);
        $this->assertSame(['1' => 'paid'], $src->changes);
    }

    public function testSkipIdsAreNotCheckedAndDoNotCountTowardsLimit(): void
    {
        $r = $this->reconciler()->run($this->source(), 30, 2, true, null, ['1', '2']);
        $this->assertSame([3, 4], array_keys($this->actions($r)));
    }

    public function testLimit(): void
    {
        $this->assertSame(2, $this->reconciler()->run($this->source(), 30, 2, true)->checked());
    }
}
