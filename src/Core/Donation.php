<?php
namespace DonationGuard\Core;

/** A pending donation as seen by the donation plugin (the "source"). */
final class Donation
{
    /** @var string */ public $id;
    /** @var string gateway key: midtrans | xendit | tripay */ public $gateway;
    /** @var string reference the gateway knows the payment by */ public $reference;
    /** @var float */ public $amount;
    /** @var string ISO-8601 */ public $createdAt;

    public function __construct(string $id, string $gateway, string $reference, float $amount, string $createdAt = '')
    {
        $this->id = $id;
        $this->gateway = $gateway;
        $this->reference = $reference;
        $this->amount = $amount;
        $this->createdAt = $createdAt;
    }
}
