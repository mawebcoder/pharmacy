<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;

/**
 * Works a purchase document's status, total cost and its order's sourcing status out of its rows (roadmap S30, owner
 * 2026-11-16), after every change to them:
 * - «پیش‌نویس» until every unit of every order item has a row, then «ثبت‌شده», then «دریافت کامل» once every row has
 *   arrived in full;
 * - the order is «آماده بسته‌بندی» when the document is received, «دریافت ناقص» once anything arrived or is missing,
 *   else «در انتظار دریافت».
 */
final class PurchaseProgress
{
    public static function refresh(OrderPurchase $purchase): void
    {
        $purchase->load(['items', 'order.items']);
        $order = $purchase->getOrder();
        /** @var list<OrderPurchaseItem> $rows */
        $rows = $purchase->items->all();

        $covered = [];
        foreach ($rows as $row) {
            $covered[$row->getOrderItemId()] = ($covered[$row->getOrderItemId()] ?? 0) + $row->getQuantity();
        }

        $fullyCovered = $order->items->every(fn (OrderItem $item): bool => ($covered[$item->getId()] ?? 0) === $item->getQuantity());
        $allReceived = $rows !== [] && array_all($rows, fn (OrderPurchaseItem $row): bool => $row->getReceivedQuantity() === $row->getQuantity());
        $anyHandled = array_any($rows, fn (OrderPurchaseItem $row): bool => $row->getReceivedQuantity() > 0 || $row->getMissingQuantity() > 0);

        $status = match (true) {
            $fullyCovered && $allReceived => PurchaseStatus::Received,
            $fullyCovered => PurchaseStatus::Registered,
            default => PurchaseStatus::Draft,
        };

        $purchase
            ->setStatus($status)
            ->setTotalCost(array_sum(array_map(fn (OrderPurchaseItem $row): int => $row->getTotalCost(), $rows)))
            ->setRegisteredAt($status === PurchaseStatus::Draft ? null : ($purchase->getRegisteredAt() ?? now()))
            ->setReceivedAt($status === PurchaseStatus::Received ? ($purchase->getReceivedAt() ?? now()) : null)
            ->save();

        $order->setSourcingStatus(match (true) {
            $status === PurchaseStatus::Received => SourcingStatus::ReadyToPack,
            $anyHandled => SourcingStatus::PartiallyReceived,
            default => SourcingStatus::AwaitingReceipt,
        })->save();
    }
}
