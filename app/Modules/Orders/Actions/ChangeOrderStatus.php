<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Catalog\Actions\ReleaseFlashDealUnits;
use App\Modules\Catalog\Actions\ReserveFlashDealUnits;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusLog;
use Illuminate\Support\Facades\DB;

/**
 * Moves an order to another status when the actor may (OrderStatus::nextFor, roadmap D3.5), sets the matching time
 * and writes order_status_logs. The only way an order's status changes. Flash deal units (S28) go back to their deals
 * when the order expires unpaid or is cancelled, and are counted again if an expired order is paid late.
 */
final class ChangeOrderStatus
{
    public function __construct(
        private ReserveFlashDealUnits $reserveFlashDealUnits,
        private ReleaseFlashDealUnits $releaseFlashDealUnits,
    ) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(Order $order, OrderStatus $to, OrderActor $actor, ?int $actorId = null, ?string $note = null): void
    {
        $from = $order->getStatus();

        if (! $from->canMoveTo($to, $actor)) {
            throw OrderRefused::statusChange($from->label(), $to->label());
        }

        DB::transaction(function () use ($order, $from, $to, $actor, $actorId, $note): void {
            $order->setStatus($to);

            match ($to) {
                OrderStatus::Paid => $order->setPaidAt(now()),
                OrderStatus::Cancelled => $order->setCancelledAt(now()),
                OrderStatus::Delivered => $order->setDeliveredAt(now()),
                default => null,
            };

            $order->save();

            self::log($order->getId(), $from, $to, $actor, $actorId, $note);
            $this->settleFlashDealUnits($order, $from, $to);
        });
    }

    private function settleFlashDealUnits(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $gone = [OrderStatus::Expired, OrderStatus::Cancelled];
        $release = in_array($to, $gone, true) && ! in_array($from, $gone, true);
        $reserveAgain = $from === OrderStatus::Expired && $to === OrderStatus::Paid;

        if (! $release && ! $reserveAgain) {
            return;
        }

        $items = OrderItem::query()
            ->where(OrderItem::COLUMN_ORDER_ID, $order->getId())
            ->whereNotNull(OrderItem::COLUMN_FLASH_DEAL_ID)
            ->get();

        foreach ($items as $item) {
            /** @var OrderItem $item */
            $release
                ? ($this->releaseFlashDealUnits)((int) $item->getFlashDealId(), $item->getQuantity())
                : ($this->reserveFlashDealUnits)((int) $item->getFlashDealId(), $item->getQuantity(), $item->getProductName(), force: true);
        }
    }

    public static function log(int $orderId, ?OrderStatus $from, OrderStatus $to, OrderActor $actor, ?int $actorId = null, ?string $note = null): void
    {
        (new OrderStatusLog)
            ->setOrderId($orderId)
            ->setFromStatus($from)
            ->setToStatus($to)
            ->setActorType($actor)
            ->setActorId($actorId)
            ->setNote($note)
            ->save();
    }
}
