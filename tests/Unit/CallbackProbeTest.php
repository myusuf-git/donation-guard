<?php
namespace DonationGuard\Tests\Unit;

use DonationGuard\Core\CallbackProbe;
use DonationGuard\Http\Response;
use DonationGuard\Tests\Support\FakePost;
use PHPUnit\Framework\TestCase;

final class CallbackProbeTest extends TestCase
{
    private function probe($res, string $gw = 'tripay'): array
    {
        return (new CallbackProbe(new FakePost($res)))->probe($gw, 'https://example.org/callback');
    }

    public function testHealthyJsonRejection(): void
    {
        $o = $this->probe(new Response(200, '{"success":false,"message":"Invalid Signature"}', 'application/json', 0.3));
        $this->assertSame(CallbackProbe::OK, $o['state']);
    }

    public function testFailures(): void
    {
        $this->assertSame(CallbackProbe::FAIL, $this->probe(new \RuntimeException('Operation timed out'))['state']);
        $this->assertSame(CallbackProbe::FAIL, $this->probe(new Response(500, 'Fatal error'))['state']);
        $this->assertSame(CallbackProbe::FAIL, $this->probe(new Response(301, ''))['state']);
        $this->assertSame(CallbackProbe::FAIL, $this->probe(new Response(404, 'nope'))['state']);
        $this->assertSame(CallbackProbe::FAIL, $this->probe(new Response(403, '<html>Just a moment...</html>', 'text/html'))['state']);
    }

    public function testWarnings(): void
    {
        $this->assertSame(CallbackProbe::WARN, $this->probe(new Response(200, 'OK', 'text/plain', 0.2))['state'], 'Tripay wants JSON');
        $this->assertSame(CallbackProbe::OK, $this->probe(new Response(200, 'OK', 'text/plain', 0.2), 'midtrans')['state']);
        $this->assertSame(CallbackProbe::WARN, $this->probe(new Response(200, '{}', 'application/json', 12.0))['state']);
    }
}
