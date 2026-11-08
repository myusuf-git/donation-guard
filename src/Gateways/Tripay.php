<?php
namespace DonationGuard\Gateways;

use DonationGuard\Core\Status;

/**
 * Tripay: GET /transaction/detail?reference={ref} (Bearer API key).
 * Reference = Tripay's own reference (e.g. "T0001000000000000006"), NOT your merchant_ref.
 */
final class Tripay extends AbstractGateway
{
    public function key(): string { return 'tripay'; }
    public function label(): string { return 'Tripay'; }

    protected function defaultBaseUrl(): string
    {
        return $this->sandbox ? 'https://tripay.co.id/api-sandbox' : 'https://tripay.co.id/api';
    }

    public function checkStatus(string $reference): Status
    {
        $res  = $this->http->get(
            $this->base() . '/transaction/detail?reference=' . rawurlencode($reference),
            ['Authorization' => 'Bearer ' . $this->secret]
        );
        $data = $res->json();
        if ($data === null) {
            return new Status(Status::ERROR, null, 'HTTP ' . $res->status . ' invalid JSON');
        }
        if (empty($data['success'])) {
            $msg = (string) ($data['message'] ?? '');
            if ($res->status === 404 || stripos($msg, 'not found') !== false) {
                return new Status(Status::NOT_FOUND, null, $msg);
            }
            return new Status(Status::ERROR, null, 'HTTP ' . $res->status . ' ' . $msg);
        }
        $tx     = $data['data'] ?? [];
        $raw    = strtoupper((string) ($tx['status'] ?? ''));
        $amount = isset($tx['amount']) ? (float) $tx['amount'] : null;

        switch ($raw) {
            case 'PAID':
                return new Status(Status::PAID, $amount, $raw);
            case 'UNPAID':
                return new Status(Status::PENDING, $amount, $raw);
            case 'EXPIRED':
                return new Status(Status::EXPIRED, $amount, $raw);
            case 'FAILED':
                return new Status(Status::FAILED, $amount, $raw);
            case 'REFUND':
                return new Status(Status::REFUNDED, $amount, $raw);
        }
        return new Status(Status::ERROR, $amount, 'unknown: ' . $raw);
    }
}
