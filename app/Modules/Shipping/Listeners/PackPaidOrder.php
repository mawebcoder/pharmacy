<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Listeners;

use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Shipping\Actions\PackOrder;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A paid order is packed into parcels in the background (roadmap S31, owner 2026-11-17).
 */
final readonly class PackPaidOrder implements ShouldQueue
{
    public function __construct(
        private PackOrder $packOrder,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $_ = ($this->packOrder)($event->orderId);
    }
}
