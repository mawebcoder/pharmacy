<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Queries\OrderReturns;

/**
 * Works out the refunds of an order's returns (roadmap S27, owner 2026-11-19), oldest first over the open ones:
 * each unit refunds what the customer paid for it (`paid_unit`, D3.5); an item's last unit back also takes its
 * rounding remainder (`paid_total − paid_unit × quantity`); the return that brings every unit back also refunds the
 * order's shipping fee. Only drafts are rewritten: an approved return keeps the amounts it was approved with.
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
            $itemsRefund = 0;

            foreach ($return->items as $returnItem) {
                $orderItem = $items->get($returnItem->getOrderItemId());
                $before = $returnedSoFar[$returnItem->getOrderItemId()] ?? 0;
                $returnedSoFar[$returnItem->getOrderItemId()] = $before + $returnItem->getQuantity();
                $refund = $returnItem->getQuantity() * $returnItem->getPaidUnit();

                if ($orderItem !== null && $before + $returnItem->getQuantity() >= $orderItem->getQuantity()) {
                    $refund += max(0, $orderItem->getPaidTotal() - $orderItem->getPaidUnit() * $orderItem->getQuantity());
                }

                if ($return->getStatus() === OrderReturnStatus::Draft) {
                    $returnItem->setRefundAmount($refund)->save();
                }

                $itemsRefund += $return->getStatus() === OrderReturnStatus::Draft ? $refund : $returnItem->getRefundAmount();
            }

            $completes = ! $feeRefunded && OrderReturns::coversAll($order, $returnedSoFar);

            if ($return->getStatus() === OrderReturnStatus::Draft) {
                $return->setItemsRefund($itemsRefund)
                    ->setShippingFeeRefund($completes ? $order->getShippingTotal() : 0)
                    ->save();
            }

            $feeRefunded = $feeRefunded || $return->getShippingFeeRefund() > 0 || $completes;
        }
    }
}
