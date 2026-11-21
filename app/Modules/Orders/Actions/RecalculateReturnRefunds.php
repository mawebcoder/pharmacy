<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Queries\OrderReturns;

/**
 * Works out the refunds of an order's returns (roadmap S27, owner 2026-11-19), oldest first over the open ones and row
 * by row: each unit refunds what the customer paid for it (`paid_unit`, D3.5), counting only units received once a
 * return is received; the unit that brings an item's last unit back also takes its rounding remainder
 * (`paid_total − paid_unit × quantity`); the return that brings every unit of the order back also refunds the order's
 * shipping fee. A refunded return keeps the amounts it was paid with.
 */
final readonly class RecalculateReturnRefunds
{
    public function __invoke(Order $order): void
    {
        $order->loadMissing('items');
        $items = $order->items->keyBy(fn ($item): int => $item->getId());
        $returnedSoFar = [];
        $feeRefunded = false;

        $returns = $order->returns()
            ->where(OrderReturn::COLUMN_STATUS, '!=', OrderReturnStatus::Cancelled)
            ->with('items')
            ->get();

        foreach ($returns as $return) {
            $rewrite = $return->getStatus() !== OrderReturnStatus::Refunded;
            $itemsRefund = 0;

            foreach ($return->items as $returnItem) {
                $orderItem = $items->get($returnItem->getOrderItemId());
                $units = $returnItem->getRefundableQuantity();
                $before = $returnedSoFar[$returnItem->getOrderItemId()] ?? 0;
                $returnedSoFar[$returnItem->getOrderItemId()] = $before + $units;
                $refund = $units * $returnItem->getPaidUnit();

                if ($orderItem !== null && $units > 0 && $before < $orderItem->getQuantity() && $before + $units >= $orderItem->getQuantity()) {
                    $refund += max(0, $orderItem->getPaidTotal() - $orderItem->getPaidUnit() * $orderItem->getQuantity());
                }

                if ($rewrite) {
                    $returnItem->setRefundAmount($refund)->save();
                }

                $itemsRefund += $rewrite ? $refund : $returnItem->getRefundAmount();
            }

            $completes = ! $feeRefunded && OrderReturns::coversAll($order, $returnedSoFar);

            if ($rewrite) {
                $return->setItemsRefund($itemsRefund)
                    ->setShippingFeeRefund($completes ? $order->getShippingTotal() : 0)
                    ->save();
            }

            $feeRefunded = $feeRefunded || $return->getShippingFeeRefund() > 0 || $completes;
        }
    }
}
