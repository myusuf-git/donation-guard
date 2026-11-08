<?php
namespace DonationGuard\Http;

/** HTTP client backed by the WordPress HTTP API. */
final class WpClient implements ClientInterface
{
    /** @var int */
    private $timeout;

    public function __construct(int $timeout = 15)
    {
        $this->timeout = $timeout;
    }

    public function get(string $url, array $headers = []): Response
    {
        $res = wp_remote_get($url, [
            'headers' => $headers + ['Accept' => 'application/json', 'User-Agent' => 'DonationGuard/' . DONATION_GUARD_VERSION],
            'timeout' => $this->timeout,
        ]);
        if (is_wp_error($res)) {
            throw new \RuntimeException('HTTP error: ' . $res->get_error_message());
        }
        return new Response((int) wp_remote_retrieve_response_code($res), (string) wp_remote_retrieve_body($res));
    }
}
