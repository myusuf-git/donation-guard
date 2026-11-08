<?php
namespace DonationGuard\Core;

/** Normalized payment status returned by every gateway adapter. */
final class Status
{
    public const PAID      = 'paid';
    public const PENDING   = 'pending';
    public const EXPIRED   = 'expired';
    public const FAILED    = 'failed';
    public const REFUNDED  = 'refunded';
    public const NOT_FOUND = 'not_found';
    public const ERROR     = 'error';

    /** @var string */
    public $value;
    /** @var float|null amount reported by the gateway */
    public $amount;
    /** @var string raw gateway status, for the audit log */
    public $raw;

    public function __construct(string $value, ?float $amount = null, string $raw = '')
    {
        $this->value  = $value;
        $this->amount = $amount;
        $this->raw    = $raw;
    }
}
