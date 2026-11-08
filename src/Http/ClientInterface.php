<?php
namespace DonationGuard\Http;

interface ClientInterface
{
    /**
     * @param array<string,string> $headers
     * @throws \RuntimeException on transport failure
     */
    public function get(string $url, array $headers = []): Response;

    /**
     * @param array<string,string> $headers
     * @throws \RuntimeException on transport failure
     */
    public function post(string $url, string $body, array $headers = [], int $timeout = 15): Response;
}
