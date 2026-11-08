<?php
namespace DonationGuard\Gateways;

use DonationGuard\Http\ClientInterface;

abstract class AbstractGateway implements GatewayInterface
{
    /** @var ClientInterface */ protected $http;
    /** @var string */ protected $secret;
    /** @var bool */ protected $sandbox;
    /** @var string|null override, used by tests and local mocks */ protected $baseUrl;

    public function __construct(ClientInterface $http, string $secret, bool $sandbox = false, ?string $baseUrl = null)
    {
        $this->http = $http;
        $this->secret = $secret;
        $this->sandbox = $sandbox;
        $this->baseUrl = $baseUrl ? rtrim($baseUrl, '/') : null;
    }

    abstract protected function defaultBaseUrl(): string;

    protected function base(): string
    {
        return $this->baseUrl ?? $this->defaultBaseUrl();
    }

    protected function basicAuth(): array
    {
        return ['Authorization' => 'Basic ' . base64_encode($this->secret . ':')];
    }
}
