<?php

declare(strict_types=1);

namespace App\Modules\Orders\Jobs;

use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\ParcelTrackingStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Every night (owner, 2026-11-19): each order on its way with a tracking code is checked with Tipax, and one Tipax has
 * delivered becomes «تحویل شد» (actor «شرکت حمل», Tipax's status as the note). Each answer is also kept for the
 * customer's order page. An order Tipax does not answer for stays as it is until the next night.
 */
final class MarkDeliveredOrders implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function handle(ParcelTrackingStatus $parcels, ChangeOrderStatus $changeStatus): void
    {
        $orders = Order::query()
            ->where(Order::COLUMN_STATUS, OrderStatus::OnTheWay)
            ->whereNotNull(Order::COLUMN_TRACKING_CODE)
            ->where(Order::COLUMN_TRACKING_CODE, '!=', '')
            ->orderBy(Order::COLUMN_ID)
            ->lazyById();

        foreach ($orders as $order) {
            /** @var Order $order */
            try {
                $parcel = $parcels->checkNow($order->getId(), (string) $order->getTrackingCode());
            } catch (TipaxRequestFailed $failed) {
                Log::warning('Tipax tracking failed for an order on its way.', ['order_id' => $order->getId(), 'reason' => $failed->getMessage()]);

                continue;
            }

            if (! $parcel->isDelivered()) {
                continue;
            }

            try {
                $changeStatus($order, OrderStatus::Delivered, OrderActor::Carrier, note: 'تیپاکس: '.($parcel->status ?? 'تحویل شده'));
            } catch (OrderRefused) {
                // Changed by staff meanwhile.
            }
        }
    }
}
