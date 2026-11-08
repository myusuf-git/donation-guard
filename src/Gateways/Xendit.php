<?php
namespace DonationGuard\Gateways;

use DonationGuard\Core\Status;

/**
 * Xendit Invoices: GET /v2/invoices?external_id={ref} (Basic auth, secret key as username).
 * Reference = the external_id you sent when creating the invoice.
 * Xendit uses the same host for test and live; the key decides the mode.
 */
final class Xendit extends AbstractGateway
{
    public function key(): string { return 'xendit'; }
    public function label(): string { return 'Xendit'; }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.xendit.co';
    }

    public function checkStatus(string $reference): Status
    {
        $res = $this->http->get($this->base() . '/v2/invoices?external_id=' . rawurlencode($reference), $this->basicAuth());
        if ($res->status === 404) {
            return new Status(Status::NOT_FOUND, null, '404');
        }
        $data = $res->json();
        if ($res->status !== 200 || $data === null) {
            $msg = is_array($data) ? (string) ($data['error_code'] ?? $data['message'] ?? '') : 'invalid JSON';
            return new Status(Status::ERROR, null, 'HTTP ' . $res->status . ' ' . $msg);
        }
        if (count($data) === 0) {
            return new Status(Status::NOT_FOUND, null, 'empty');
        }
        // Several invoices may share an external_id (retries). Paid wins, then pending, then newest.
        $best = null;
        $rank = ['SETTLED' => 3, 'PAID' => 3, 'PENDING' => 2, 'EXPIRED' => 1];
        foreach ($data as $inv) {
            $s = strtoupper((string) ($inv['status'] ?? ''));
            if ($best === null || ($rank[$s] ?? 0) > ($rank[strtoupper((string) $best['status'])] ?? 0)) {
                $best = $inv;
            }
        }
        $raw    = strtoupper((string) $best['status']);
        $amount = isset($best['paid_amount']) ? (float) $best['paid_amount'] : (isset($best['amount']) ? (float) $best['amount'] : null);

        switch ($raw) {
            case 'PAID':
            case 'SETTLED':
                return new Status(Status::PAID, $amount, $raw);
            case 'PENDING':
                return new Status(Status::PENDING, $amount, $raw);
            case 'EXPIRED':
                return new Status(Status::EXPIRED, $amount, $raw);
        }
        return new Status(Status::ERROR, $amount, 'unknown: ' . $raw);
    }
}
