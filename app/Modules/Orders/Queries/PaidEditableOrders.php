<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

/**
 * A paid order staff may still edit (owner, 2026-11-19): paid or being prepared, not gone out, and its purchase
 * document not registered yet (a registered purchase would no longer match). Locked for the edit.
 */
final readonly class PaidEditableOrders
{
    /**
     * Call inside a transaction: the order row is locked until it commits.
     *
     * @return array{order_id: int, number: string, customer_id: int, total: int, difference_due: int, items: list<array{item_id: int, variant_id: int, quantity: int, unit_price: int, paid_unit: int, paid_total: int, flash_deal_id: int|null}>}|null
     */
    public function lockFor(int $orderId): ?array
    {
        $order = Order::query()->lockForUpdate()->find($orderId);

        if (! $order instanceof Order || ! self::editable($order)) {
            return null;
        }

        return [
            'order_id' => $order->getId(),
            'number' => (string) $order->getNumber(),
            'customer_id' => $order->getCustomerId(),
            'total' => $order->getTotal(),
            'difference_due' => $order->getDifferenceDue(),
            'items' => array_values($order->items()->get()->map(fn (OrderItem $item): array => [
                'item_id' => $item->getId(),
                'variant_id' => (int) $item->getVariantId(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'paid_unit' => $item->getPaidUnit(),
                'paid_total' => $item->getPaidTotal(),
                'flash_deal_id' => $item->getFlashDealId(),
            ])->all()),
        ];
    }

    public static function editable(Order $order): bool
    {
        return in_array($order->getStatus(), [OrderStatus::Paid, OrderStatus::Processing], true)
            && $order->getPaymentStatus() === PaymentStatus::Paid
            && in_array($order->getPurchase()?->getStatus(), [null, PurchaseStatus::Draft], true);
    }
}
