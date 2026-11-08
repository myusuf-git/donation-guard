<?php
namespace DonationGuard\Tests\Unit\Gateways;

use DonationGuard\Core\Status;
use DonationGuard\Gateways\Xendit;
use DonationGuard\Tests\Support\FakeHttp;
use PHPUnit\Framework\TestCase;

final class XenditTest extends TestCase
{
    private function check(string $fixture, int $http = 200): Status
    {
        return (new Xendit(new FakeHttp(['/v2/invoices' => [$http, 'xendit/' . $fixture]]), 'xnd_development_TEST'))->checkStatus('DON-2001');
    }

    public function testPaid(): void
    {
        $s = $this->check('paid.json');
        $this->assertSame(Status::PAID, $s->value);
        $this->assertSame(100000.0, $s->amount);
    }

    public function testPaidInvoiceWinsOverExpiredRetry(): void
    {
        $this->assertSame(Status::PAID, $this->check('expired_then_paid.json')->value);
    }

    public function testOtherStates(): void
    {
        $this->assertSame(Status::PENDING, $this->check('pending.json')->value);
        $this->assertSame(Status::EXPIRED, $this->check('expired.json')->value);
        $this->assertSame(Status::NOT_FOUND, $this->check('empty.json')->value);
        $this->assertSame(Status::ERROR, $this->check('unauthorized.json', 401)->value);
    }

    public function testUrl(): void
    {
        $fake = new FakeHttp(['/v2/invoices' => [200, 'xendit/paid.json']]);
        (new Xendit($fake, 'k'))->checkStatus('DON 1');
        $this->assertSame('https://api.xendit.co/v2/invoices?external_id=DON%201', $fake->calls[0]['url']);
    }
}
