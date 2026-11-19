<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A cancelled order's return was registered with Tipax (roadmap S35, owner 2026-11-19): Notifications texts the
 * customer the return's tracking code.
 */
final readonly class OrderReturnRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
        public string $trackingCode,
    ) {}
}
