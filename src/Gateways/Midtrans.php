<?php
namespace DonationGuard\Gateways;

use DonationGuard\Core\Status;

/**
 * Midtrans Core API: GET /v2/{order_id}/status (Basic auth, server key as username).
 * Reference = the order_id sent to Midtrans.
 */
final class Midtrans extends AbstractGateway
{
    public function key(): string { return 'midtrans'; }
    public function label(): string { return 'Midtrans'; }

    protected function defaultBaseUrl(): string
    {
        return $this->sandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
    }

    public function checkStatus(string $reference): Status
    {
        $res  = $this->http->get($this->base() . '/v2/' . rawurlencode($reference) . '/status', $this->basicAuth());
        $data = $res->json();
        if ($data === null) {
            return new Status(Status::ERROR, null, 'HTTP ' . $res->status . ' invalid JSON');
        }
        // Midtrans returns HTTP 200 with its own status_code in the body.
        $code = (string) ($data['status_code'] ?? $res->status);
        if ($code === '404') {
            return new Status(Status::NOT_FOUND, null, '404');
        }
        $tx = strtolower((string) ($data['transaction_status'] ?? ''));
        if ($tx === '') {
            return new Status(Status::ERROR, null, 'status_code ' . $code . ': ' . ($data['status_message'] ?? ''));
        }
        $fraud  = strtolower((string) ($data['fraud_status'] ?? 'accept'));
        $amount = isset($data['gross_amount']) ? (float) $data['gross_amount'] : null;
        $raw    = $tx . ($fraud !== 'accept' ? '/' . $fraud : '');

        switch ($tx) {
            case 'settlement':
                return new Status(Status::PAID, $amount, $raw);
            case 'capture':
                return new Status($fraud === 'accept' ? Status::PAID : Status::PENDING, $amount, $raw);
            case 'pending':
            case 'authorize':
                return new Status(Status::PENDING, $amount, $raw);
            case 'expire':
                return new Status(Status::EXPIRED, $amount, $raw);
            case 'cancel':
            case 'deny':
            case 'failure':
                return new Status(Status::FAILED, $amount, $raw);
            case 'refund':
            case 'partial_refund':
                return new Status(Status::REFUNDED, $amount, $raw);
        }
        return new Status(Status::ERROR, $amount, 'unknown: ' . $raw);
    }
}
