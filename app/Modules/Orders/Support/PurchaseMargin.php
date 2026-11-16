<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchaseItem;

/**
 * The final margin (roadmap S30, owner 2026-11-16): what the customer paid for the order's items less what the
 * purchase document's rows cost. Final only once the document covers every unit («ثبت‌شده» or later); until then the
 * order shows the checkout estimate (order_items.unit_cost), which is never overwritten.
 */
final class PurchaseMargin
{
    /**
     * What the rows for this item cost (rial), or null when it has none.
     */
    public static function itemCost(OrderItem $item): ?int
    {
        $rows = $item->purchaseItems;

        return $rows->isEmpty() ? null : (int) $rows->sum(fn (OrderPurchaseItem $row): int => $row->getTotalCost());
    }

    /**
     * Paid for the item less its rows' cost, once the document is final; null before.
     */
    public static function itemMargin(Order $order, OrderItem $item): ?int
    {
        $cost = self::itemCost($item);

        return self::isFinal($order) && $cost !== null ? $item->getPaidTotal() - $cost : null;
    }

    /**
     * Paid for the items less the document's total cost, once the document is final; null before.
     */
    public static function orderMargin(Order $order): ?int
    {
        $purchase = $order->getPurchase();

        return self::isFinal($order) && $purchase !== null ? $order->getItemsPaidTotal() - $purchase->getTotalCost() : null;
    }

    public static function isFinal(Order $order): bool
    {
        $status = $order->getPurchase()?->getStatus();

        return $status !== null && $status !== PurchaseStatus::Draft;
    }
}
