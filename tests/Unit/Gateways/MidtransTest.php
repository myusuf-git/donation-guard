<?php
namespace DonationGuard\Tests\Unit\Gateways;

use DonationGuard\Core\Status;
use DonationGuard\Gateways\Midtrans;
use DonationGuard\Tests\Support\FakeHttp;
use PHPUnit\Framework\TestCase;

final class MidtransTest extends TestCase
{
    private function gw(string $fixture, int $http = 200): array
    {
        $fake = new FakeHttp(['/status' => [$http, 'midtrans/' . $fixture]]);
        return [new Midtrans($fake, 'SB-Mid-server-TEST', true), $fake];
    }

    public function testSettlementIsPaidWithAmount(): void
    {
        [$g, $fake] = $this->gw('settlement.json');
        $s = $g->checkStatus('DON-1001');
        $this->assertSame(Status::PAID, $s->value);
        $this->assertSame(50000.0, $s->amount);
        $this->assertSame('https://api.sandbox.midtrans.com/v2/DON-1001/status', $fake->calls[0]['url']);
        $this->assertSame('Basic ' . base64_encode('SB-Mid-server-TEST:'), $fake->calls[0]['headers']['Authorization']);
    }

    public function testProductionHost(): void
    {
        $fake = new FakeHttp(['/status' => [200, 'midtrans/settlement.json']]);
        (new Midtrans($fake, 'k', false))->checkStatus('A/B');
        $this->assertSame('https://api.midtrans.com/v2/A%2FB/status', $fake->calls[0]['url']);
    }

    public function testMapping(): void
    {
        $this->assertSame(Status::PENDING, $this->gw('pending.json')[0]->checkStatus('x')->value);
        $this->assertSame(Status::EXPIRED, $this->gw('expire.json')[0]->checkStatus('x')->value);
        $this->assertSame(Status::PENDING, $this->gw('capture_challenge.json')[0]->checkStatus('x')->value, 'fraud challenge must not be paid');
        $this->assertSame(Status::NOT_FOUND, $this->gw('not_found.json')[0]->checkStatus('x')->value);
        $this->assertSame(Status::ERROR, $this->gw('unauthorized.json')[0]->checkStatus('x')->value);
    }
}
