<?php

declare(strict_types=1);

namespace App\Modules\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The customer asked for a return from their account (roadmap S27, owner 2026-11-19): staff are texted.
 */
final readonly class OrderReturnRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
        public string $returnNumber,
    ) {}
}
