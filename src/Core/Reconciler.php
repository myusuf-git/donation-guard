<?php
namespace DonationGuard\Core;

use DonationGuard\Gateways\GatewayInterface;
use DonationGuard\Sources\SourceInterface;

/**
 * Asks each payment gateway for the real status of pending donations
 * and (unless in dry-run) updates the donation plugin accordingly.
 *
 * Safety rules:
 *  - Only "paid with matching amount" is ever marked as paid.
 *  - Amount mismatches are never changed automatically.
 *  - Gateway errors / unknown references are left untouched.
 */
final class Reconciler
{
    /** @var array<string,GatewayInterface> */
    private $gateways = [];
    /** @var bool */
    private $markExpiredAsFailed;
    /** @var float */
    private $tolerance;

    public function __construct(bool $markExpiredAsFailed = true, float $tolerance = 0.01)
    {
        $this->markExpiredAsFailed = $markExpiredAsFailed;
        $this->tolerance = $tolerance;
    }

    public function addGateway(GatewayInterface $g): self
    {
        $this->gateways[$g->key()] = $g;
        return $this;
    }

    public function run(SourceInterface $source, int $olderThanMinutes, int $limit, bool $dryRun, ?string $now = null): Report
    {
        $report = new Report($dryRun, $now ?? gmdate('c'));
        foreach ($source->findPending($olderThanMinutes, $limit) as $donation) {
            $report->add($this->reconcileOne($source, $donation, $dryRun));
        }
        return $report;
    }

    public function reconcileOne(SourceInterface $source, Donation $d, bool $dryRun): Outcome
    {
        $gateway = $this->gateways[$d->gateway] ?? null;
        if ($gateway === null) {
            return new Outcome($d, Outcome::SKIPPED, null, $d->gateway === ''
                ? 'Paid with a method Donation Guard does not check'
                : 'Gateway "' . $d->gateway . '" is not enabled');
        }
        if ($d->reference === '') {
            return new Outcome($d, Outcome::UNRESOLVED, null, 'Donation has no gateway reference');
        }

        try {
            $status = $gateway->checkStatus($d->reference);
        } catch (\Throwable $e) {
            return new Outcome($d, Outcome::UNRESOLVED, new Status(Status::ERROR), $e->getMessage());
        }

        switch ($status->value) {
            case Status::PAID:
                if ($status->amount !== null && abs($status->amount - $d->amount) > $this->tolerance) {
                    return new Outcome($d, Outcome::MISMATCH, $status, sprintf(
                        'Gateway amount %s differs from donation amount %s',
                        $this->fmt($status->amount), $this->fmt($d->amount)
                    ));
                }
                if ($dryRun) {
                    return new Outcome($d, Outcome::WOULD_PAY, $status);
                }
                try {
                    $source->markPaid($d, 'Confirmed paid by ' . $gateway->label() . ' (Donation Guard)');
                } catch (\Throwable $e) {
                    return new Outcome($d, Outcome::UNRESOLVED, $status, 'Paid at gateway but update failed: ' . $e->getMessage());
                }
                return new Outcome($d, Outcome::MARKED_PAID, $status);

            case Status::EXPIRED:
            case Status::FAILED:
                if (!$this->markExpiredAsFailed) {
                    return new Outcome($d, Outcome::STILL_PENDING, $status, 'Expired/failed at gateway; auto-fail disabled');
                }
                if ($dryRun) {
                    return new Outcome($d, Outcome::WOULD_FAIL, $status);
                }
                try {
                    $source->markFailed($d, $gateway->label() . ' reports "' . $status->raw . '" (Donation Guard)');
                } catch (\Throwable $e) {
                    return new Outcome($d, Outcome::UNRESOLVED, $status, 'Update to failed did not complete: ' . $e->getMessage());
                }
                return new Outcome($d, Outcome::MARKED_FAILED, $status);

            case Status::PENDING:
                return new Outcome($d, Outcome::STILL_PENDING, $status);

            case Status::REFUNDED:
                return new Outcome($d, Outcome::UNRESOLVED, $status, 'Refunded at gateway but pending locally');

            case Status::NOT_FOUND:
                return new Outcome($d, Outcome::UNRESOLVED, $status, 'Reference not found at gateway');

            default:
                return new Outcome($d, Outcome::UNRESOLVED, $status, 'Gateway error: ' . $status->raw);
        }
    }

    private function fmt(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
