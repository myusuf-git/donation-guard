<?php
namespace DonationGuard\Tests\Support;

use DonationGuard\Http\ClientInterface;
use DonationGuard\Http\Response;

/** Replays recorded gateway responses. Maps URL substring => [status, fixture file]. */
final class FakeHttp implements ClientInterface
{
    /** @var array<string,array{0:int,1:string}> */
    private $routes;
    /** @var array<int,array{url:string,headers:array}> */
    public $calls = [];

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function get(string $url, array $headers = []): Response
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers];
        foreach ($this->routes as $needle => [$status, $file]) {
            if (strpos($url, $needle) !== false) {
                if ($file === 'THROW') {
                    throw new \RuntimeException('connection timed out');
                }
                return new Response($status, (string) file_get_contents(__DIR__ . '/../fixtures/' . $file));
            }
        }
        throw new \RuntimeException('No fake route for ' . $url);
    }

    public function post(string $url, string $body, array $headers = [], int $timeout = 15): Response
    {
        throw new \LogicException('not used');
    }
}
