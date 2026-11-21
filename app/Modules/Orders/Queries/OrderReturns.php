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
use Illuminate\Support\Carbon;

/**
 * What an order's returns hold (roadmap S27, owner 2026-11-19): which units may still come back, whether the order is
 * returned in full or in part, and the refund and Tipax price over its open (not cancelled) returns.
 */
final readonly class OrderReturns
{
    public function __construct(private TipaxShipments $shipments) {}

    /**
     * A paid order not sent yet (its items need no pickup) or a delivered one, with units no open return holds. A
     * cancelled order has no returns: it is refunded on the order (owner, 2026-11-19).
     */
    public function canReturn(Order $order): bool
    {
        return in_array($order->getStatus(), [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Delivered], true)
            && array_sum($this->remaining($order)) > 0;
    }

    /**
     * The order's returns for the customer's order page (owner, 2026-11-19): number, status, the rows with their reasons,
     * the return tracking code and, once refunded, the amount.
     *
     * @return list<array{number: string, status: string, rows: list<array{title: string, quantity: int, reason: string}>, tracking_code: string|null, refund: int|null}>
     */
    public function forCustomer(Order $order): array
    {
        $order->loadMissing('items');
        $items = $order->items->keyBy(fn (OrderItem $item): int => $item->getId());

        return array_values($order->returns()->with('items')->get()->map(fn (OrderReturn $return): array => [
            'number' => $return->getNumber(),
            'status' => $return->getStatus()->customerLabel(),
            'rows' => array_values($return->items->map(fn (OrderReturnItem $row): array => [
                'title' => trim(($items->get($row->getOrderItemId())?->getProductName() ?? '').' '.($items->get($row->getOrderItemId())?->getVariantTitle() ?? '')),
                'quantity' => $row->getQuantity(),
                'reason' => $row->getReason()->label(),
            ])->all()),
            'tracking_code' => $return->getTrackingCodes()[0] ?? null,
            'refund' => $return->getStatus() === OrderReturnStatus::Refunded ? $return->getRefundTotal() : null,
        ])->all());
    }

    /**
     * The order's items with units left to return, and how many (the customer's request form).
     *
     * @return array<int, array{title: string, left: int}>
     */
    public function itemsLeftFor(Order $order): array
    {
        $remaining = $this->remaining($order);
        $options = [];

        foreach ($order->items as $item) {
            if (($remaining[$item->getId()] ?? 0) > 0) {
                $options[$item->getId()] = ['title' => trim($item->getProductName().' '.($item->getVariantTitle() ?? '')), 'left' => $remaining[$item->getId()]];
            }
        }

        return $options;
    }

    /**
     * A return's number (PH-…-R2) for links from other modules, or null when it is gone.
     */
    public function numberOf(int $orderReturnId): ?string
    {
        return OrderReturn::query()->with('order')->find($orderReturnId)?->getNumber();
    }

    /**
     * How long after delivery the customer may ask for a return from their account (owner, 2026-11-19).
     */
    public const int CUSTOMER_REQUEST_HOURS = 24;

    /**
     * The customer may ask for a return: delivered less than 24 hours ago, with units no open return holds.
     */
    public function customerMayRequest(Order $order): bool
    {
        return $order->getStatus() === OrderStatus::Delivered
            && $order->getDeliveredAt() !== null
            && now()->lessThan($this->customerRequestUntil($order))
            && array_sum($this->remaining($order)) > 0;
    }

    /**
     * When the customer can no longer ask for a return: 24 hours after delivery.
     */
    public function customerRequestUntil(Order $order): ?Carbon
    {
        return $order->getDeliveredAt()?->copy()->addHours(self::CUSTOMER_REQUEST_HOURS);
    }

    /**
     * The order's parcels left us (registered with Tipax or given a code by hand): its items come back through Tipax.
     */
    public function wentOut(Order $order): bool
    {
        return in_array($order->getStatus(), [OrderStatus::OnTheWay, OrderStatus::Delivered], true)
            || $order->getTrackingCode() !== null
            || $this->shipments->wasSent($order->getId());
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
