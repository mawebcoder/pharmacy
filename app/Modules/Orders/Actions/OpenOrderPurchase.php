<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Support\PurchaseProgress;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت سند خرید» (roadmap S25, owner 2026-11-16): an admin opens the order's purchase document, empty; its rows are
 * typed by hand (SavePurchaseItem). One per order: opening it again returns the same document. Only for a paid order
 * that is not cancelled.
 */
final readonly class OpenOrderPurchase
{
    /**
     * @throws PurchaseRefused
     */
    public function __invoke(Order $order, int $adminId): OrderPurchase
    {
        return DB::transaction(function () use ($order, $adminId): OrderPurchase {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getId());
            $existing = OrderPurchase::query()->where(OrderPurchase::COLUMN_ORDER_ID, $order->getId())->first();

            if ($existing instanceof OrderPurchase) {
                return $existing;
            }

            if (! $order->getStatus()->isPurchase() || $order->getStatus() === OrderStatus::Cancelled) {
                throw PurchaseRefused::orderNotPaid();
            }

            $purchase = (new OrderPurchase)
                ->setOrderId($order->getId())
                ->setStatus(PurchaseStatus::Draft)
                ->setOpenedBy($adminId);
            $purchase->save();

            PurchaseProgress::refresh($purchase);

            return $purchase;
        });
    }
}
