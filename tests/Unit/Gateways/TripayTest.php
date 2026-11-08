<?php
namespace DonationGuard\Tests\Unit\Gateways;

use DonationGuard\Core\Status;
use DonationGuard\Gateways\Tripay;
use DonationGuard\Tests\Support\FakeHttp;
use PHPUnit\Framework\TestCase;

final class TripayTest extends TestCase
{
    private function check(string $fixture, int $http = 200): Status
    {
        return (new Tripay(new FakeHttp(['/transaction/detail' => [$http, 'tripay/' . $fixture]]), 'DEV-key', true))->checkStatus('T1');
    }

    public function testPaid(): void
    {
        $s = $this->check('paid.json');
        $this->assertSame(Status::PAID, $s->value);
        $this->assertSame(150000.0, $s->amount);
    }

    public function testOtherStates(): void
    {
        $this->assertSame(Status::PENDING, $this->check('unpaid.json')->value);
        $this->assertSame(Status::EXPIRED, $this->check('expired.json')->value);
        $this->assertSame(Status::REFUNDED, $this->check('refund.json')->value);
        $this->assertSame(Status::NOT_FOUND, $this->check('not_found.json', 404)->value);
    }

    public function testSandboxUrlAndBearer(): void
    {
        $fake = new FakeHttp(['/transaction/detail' => [200, 'tripay/paid.json']]);
        (new Tripay($fake, 'DEV-key', true))->checkStatus('T0001');
        $this->assertSame('https://tripay.co.id/api-sandbox/transaction/detail?reference=T0001', $fake->calls[0]['url']);
        $this->assertSame('Bearer DEV-key', $fake->calls[0]['headers']['Authorization']);
    }
}
