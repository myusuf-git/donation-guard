<?php
namespace DonationGuard\Http;

interface ClientInterface
{
    /**
     * @param array<string,string> $headers
     * @throws \RuntimeException on transport failure
     */
    public function get(string $url, array $headers = []): Response;
}
