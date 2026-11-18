<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order became paid (roadmap D13.5); other modules react to it, e.g. Shipping packs it into parcels (S31).
 * Dispatched once the payment's transaction has committed.
 */
final readonly class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
    ) {}
}
