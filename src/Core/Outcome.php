<?php
namespace DonationGuard\Core;

/** What the reconciler decided for one donation. */
final class Outcome
{
    public const MARKED_PAID   = 'marked_paid';
    public const MARKED_FAILED = 'marked_failed';
    public const WOULD_PAY     = 'would_mark_paid';   // dry-run
    public const WOULD_FAIL    = 'would_mark_failed'; // dry-run
    public const STILL_PENDING = 'still_pending';
    public const MISMATCH      = 'amount_mismatch';
    public const UNRESOLVED    = 'unresolved';
    public const SKIPPED       = 'skipped';
    public const NO_INVOICE    = 'no_payment_code';      // gateway never issued a payment code
    public const EXPIRED_KEPT  = 'expired_left_pending'; // expired at gateway, source has no "failed" status

    /** @var Donation */ public $donation;
    /** @var string */ public $action;
    /** @var Status|null */ public $status;
    /** @var string */ public $note;

    public function __construct(Donation $donation, string $action, ?Status $status = null, string $note = '')
    {
        $this->donation = $donation;
        $this->action = $action;
        $this->status = $status;
        $this->note = $note;
    }
}
