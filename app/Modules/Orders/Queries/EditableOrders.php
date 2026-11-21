<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

/**
 * An unpaid order as editing reads it (owner, 2026-11-19): locked, with its customer, address, coupon, staff discount
 * and items (with the flash deals their units were counted against); null once it can no longer be paid.
 */
final readonly class EditableOrders
{
    /**
     * Call inside a transaction: the order row is locked until it commits.
     *
     * @return array{order_id: int, number: string, customer_id: int, address_id: int|null, coupon_code: string|null, source: OrderSource, manual_discount: int, manual_discount_reason: string|null, customer_note: string|null, items: list<array{variant_id: int, quantity: int, flash_deal_id: int|null}>}|null
     */
    public function lockFor(int $orderId): ?array
    {
        $order = Order::query()->lockForUpdate()->find($orderId);

        if (! $order instanceof Order || ! $order->canBePaid()) {
            return null;
        }

        return [
            'order_id' => $order->getId(),
            'number' => (string) $order->getNumber(),
            'customer_id' => $order->getCustomerId(),
            'address_id' => $order->getAddressId(),
            'coupon_code' => $order->getCouponCode(),
            'source' => $order->getSource(),
            'manual_discount' => $order->getManualDiscount(),
            'manual_discount_reason' => $order->getManualDiscountReason(),
            'customer_note' => $order->getCustomerNote(),
            'items' => array_values($order->items()->get()->map(fn (OrderItem $item): array => [
                'variant_id' => (int) $item->getVariantId(),
                'quantity' => $item->getQuantity(),
                'flash_deal_id' => $item->getFlashDealId(),
            ])->all()),
        ];
    }
}
