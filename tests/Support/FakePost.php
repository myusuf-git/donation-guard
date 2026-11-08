<?php
namespace DonationGuard\Tests\Support;

use DonationGuard\Http\ClientInterface;
use DonationGuard\Http\Response;

final class FakePost implements ClientInterface
{
    /** @var Response|\Throwable */
    private $next;

    public function __construct($next) { $this->next = $next; }
    public function get(string $url, array $headers = []): Response { throw new \LogicException('not used'); }
    public function post(string $url, string $body, array $headers = [], int $timeout = 15): Response
    {
        if ($this->next instanceof \Throwable) {
            throw $this->next;
        }
        return $this->next;
    }
}
