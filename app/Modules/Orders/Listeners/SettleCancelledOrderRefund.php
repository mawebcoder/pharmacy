<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Actions\MarkOrderRefunded;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Events\RefundApproved;
use Illuminate\Support\Facades\Log;

/**
 * A cancelled order's refund was approved (owner, 2026-11-19): the order becomes «بازگشت وجه شد» with the amounts paid
 * back for the items and the shipping, and the transfer reference.
 */
final readonly class SettleCancelledOrderRefund
{
    public function __construct(private MarkOrderRefunded $markRefunded) {}

    public function handle(RefundApproved $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($event->source !== RefundSource::OrderCancel || ! $order instanceof Order) {
            return;
        }

        try {
            ($this->markRefunded)($order, $event->reference, (int) ($event->details['items'] ?? 0), (int) ($event->details['shipping'] ?? 0));
        } catch (OrderRefused $refused) {
            Log::warning('A cancelled order refund could not settle the order.', ['order_id' => $order->getId(), 'reason' => $refused->getMessage()]);
        }
    }
}
