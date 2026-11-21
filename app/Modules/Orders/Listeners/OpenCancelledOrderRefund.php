<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\CreateRefund;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Queries\OrderPayments;
use Illuminate\Support\Facades\Log;

/**
 * A paid order was cancelled (owner, 2026-11-19): its refund record opens in «بازگشت وجه‌ها», waiting for approval, for
 * what the customer paid for the items (less free shipping already used when it had been delivered) and the shipping,
 * kept apart so staff may lower either on approval.
 */
final readonly class OpenCancelledOrderRefund
{
    public function __construct(
        private CreateRefund $createRefund,
        private OrderPayments $payments,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $order = Order::query()->find($event->orderId);

        if (! $order instanceof Order || $order->getPaymentStatus() !== PaymentStatus::RefundPending || $this->payments->refunds($order->getId()) !== []) {
            return;
        }

        $items = max(0, $order->getItemsPaidTotal() - $order->getDeliveredFreeShipping());
        $shipping = $order->getShippingTotal();

        try {
            ($this->createRefund)(
                $order->getId(),
                min($items + $shipping, $this->payments->refundable($order->getId())),
                RefundSource::OrderCancel,
                'لغو سفارش '.$order->getNumber(),
                details: ['items' => $items, 'shipping' => $shipping],
            );
        } catch (RefundRefused $refused) {
            Log::warning('No refund record for a cancelled order.', ['order_id' => $order->getId(), 'reason' => $refused->getMessage()]);
        }
    }
}
