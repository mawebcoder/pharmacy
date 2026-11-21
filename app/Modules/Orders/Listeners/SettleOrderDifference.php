<?php

declare(strict_types=1);

namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Events\DifferencePaymentSucceeded;
use Illuminate\Support\Facades\DB;

/**
 * The customer paid the difference after an edit (owner, 2026-11-19): what they owe goes down by it, and the order may
 * go out once nothing is owed.
 */
final class SettleOrderDifference
{
    public function handle(DifferencePaymentSucceeded $event): void
    {
        DB::transaction(function () use ($event): void {
            $order = Order::query()->lockForUpdate()->find($event->orderId);

            if ($order instanceof Order) {
                $due = max(0, $order->getDifferenceDue() - $event->amount);
                $order->setDifferenceDue($due)->setDifferenceDueUntil($due === 0 ? null : $order->getDifferenceDueUntil())->save();
            }
        });
    }
}
