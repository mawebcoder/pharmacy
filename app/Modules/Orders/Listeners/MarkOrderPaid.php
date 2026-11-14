<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Cart\Actions\SettleOrderCoupon;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\PaymentSucceeded;
use Illuminate\Support\Facades\DB;

/**
 * The payment was verified (roadmap D13.5): the order becomes paid and its coupon used. Runs once per order.
 */
final readonly class MarkOrderPaid
{
    public function __construct(
        private ChangeOrderStatus $changeStatus,
        private SettleOrderCoupon $settleCoupon,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        DB::transaction(function () use ($event): void {
            $order = Order::query()->lockForUpdate()->find($event->orderId);

            if (! $order instanceof Order || ! $order->getStatus()->canMoveTo(OrderStatus::Paid, OrderActor::System)) {
                return;
            }

            ($this->changeStatus)($order, OrderStatus::Paid, OrderActor::System);
            $order->setPaymentStatus(PaymentStatus::Paid)->save();
            ($this->settleCoupon)($order->getId(), paid: true);
        });
    }
}
