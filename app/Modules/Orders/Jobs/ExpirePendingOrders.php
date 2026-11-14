<?php

declare(strict_types=1);

namespace App\Modules\Orders\Jobs;

use App\Modules\Cart\Actions\SettleOrderCoupon;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Orders whose payment window passed unpaid become expired and their coupon reservation is reverted (roadmap D3.5,
 * owner 2026-11-14). Runs every minute.
 */
final class ExpirePendingOrders implements ShouldQueue
{
    use Queueable;

    public function handle(ChangeOrderStatus $changeStatus, SettleOrderCoupon $settleCoupon): void
    {
        Order::query()
            ->where(Order::COLUMN_STATUS, OrderStatus::Pending)
            ->where(Order::COLUMN_EXPIRES_AT, '<=', now())
            ->each(function (Order $order) use ($changeStatus, $settleCoupon): void {
                $changeStatus($order, OrderStatus::Expired, OrderActor::System);
                $order->setPaymentStatus(PaymentStatus::Unpaid)->save();
                $settleCoupon($order->getId(), paid: false);
            });
    }
}
