<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\ReturnScope;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnItem;
use App\Modules\Shipping\Queries\TipaxShipments;

/**
 * What an order's returns hold (roadmap S27, owner 2026-11-19): which units may still come back, whether the order is
 * returned in full or in part, and the refund and Tipax price over its open (not cancelled) returns.
 */
final readonly class OrderReturns
{
    public function __construct(private TipaxShipments $shipments) {}

    /**
     * A delivered order, or a cancelled one that had gone out (registered with Tipax or given a code by hand), with
     * units no open return holds.
     */
    public function canReturn(Order $order): bool
    {
        $wentOut = match ($order->getStatus()) {
            OrderStatus::Delivered => true,
            OrderStatus::Cancelled => $order->getTrackingCode() !== null || $this->shipments->wasSent($order->getId()),
            default => false,
        };

        return $wentOut && array_sum($this->remaining($order)) > 0;
    }

    /**
     * Units of each item no open return holds.
     *
     * @return array<int, int> by order item id
     */
    public function remaining(Order $order): array
    {
        $returned = $this->returned($order->getId());
        $remaining = [];

        foreach ($order->items as $item) {
            $remaining[$item->getId()] = max(0, $item->getQuantity() - ($returned[$item->getId()] ?? 0));
        }

        return $remaining;
    }

    /**
     * Units of each item in the order's open returns.
     *
     * @return array<int, int> by order item id
     */
    public function returned(int $orderId): array
    {
        /** @var array<int, int> */
        return OrderReturnItem::query()
            ->whereHas('orderReturn', fn ($query) => $query
                ->where(OrderReturn::COLUMN_ORDER_ID, $orderId)
                ->where(OrderReturn::COLUMN_STATUS, '!=', OrderReturnStatus::Cancelled))
            ->selectRaw(OrderReturnItem::COLUMN_ORDER_ITEM_ID.', SUM('.OrderReturnItem::COLUMN_QUANTITY.') as returned')
            ->groupBy(OrderReturnItem::COLUMN_ORDER_ITEM_ID)
            ->pluck('returned', OrderReturnItem::COLUMN_ORDER_ITEM_ID)
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * «مرجوعی کامل» when the open returns hold every unit, «مرجوعی جزئی» when some, null when none.
     */
    public function scope(Order $order): ?ReturnScope
    {
        $returned = $this->returned($order->getId());

        if (array_sum($returned) === 0) {
            return null;
        }

        return self::coversAll($order, $returned) ? ReturnScope::Full : ReturnScope::Partial;
    }

    /**
     * The refund and Tipax's return price over the order's open returns (the price is null when none was priced).
     *
     * @return array{refund: int, return_shipping: int|null}
     */
    public function totals(Order $order): array
    {
        $refund = 0;
        $shipping = null;

        foreach ($order->returns()->where(OrderReturn::COLUMN_STATUS, '!=', OrderReturnStatus::Cancelled)->get() as $return) {
            $refund += $return->getRefundTotal();

            if ($return->getReturnShippingPrice() !== null) {
                $shipping = ($shipping ?? 0) + $return->getReturnShippingPrice();
            }
        }

        return ['refund' => $refund, 'return_shipping' => $shipping];
    }

    /**
     * Whether the units add up to every unit of every item of the order.
     *
     * @param  array<int, int>  $units  by order item id
     */
    public static function coversAll(Order $order, array $units): bool
    {
        return $order->items->every(fn (OrderItem $item): bool => ($units[$item->getId()] ?? 0) >= $item->getQuantity());
    }
}
