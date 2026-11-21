<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An unpaid order's payment link should reach the customer by SMS (owner, 2026-11-19): when staff place an order in
 * the panel, and on «ارسال دوباره لینک پرداخت».
 */
final readonly class OrderPaymentLinkRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $orderId) {}
}
