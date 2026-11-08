<?php
namespace DonationGuard\Gateways;

use DonationGuard\Core\Status;

/**
 * One file per payment gateway. To add a gateway:
 *  1. implement this interface in src/Gateways/YourGateway.php
 *  2. add recorded JSON fixtures in tests/fixtures/yourgateway/
 *  3. add a unit test in tests/Unit/Gateways/
 */
interface GatewayInterface
{
    /** Stable machine key, e.g. "midtrans". */
    public function key(): string;

    /** Human label, e.g. "Midtrans". */
    public function label(): string;

    /** Ask the gateway for the real status of one payment. Must not throw for "not found". */
    public function checkStatus(string $reference): Status;
}
