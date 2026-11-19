<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The order was handed to the carrier with its tracking code (roadmap S24, S37; owner 2026-11-19), sent after the
 * status change commits. Notifications tells the customer by SMS.
 */
final readonly class OrderOnItsWay implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
    ) {}
}
