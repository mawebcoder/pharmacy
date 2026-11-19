<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The order was cancelled, by the customer or an admin (roadmap S37, owner 2026-11-19), sent after the status change
 * commits. Notifications tells the customer by SMS.
 */
final readonly class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
    ) {}
}
