<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusLog;
use Illuminate\Support\Facades\DB;

/**
 * Moves an order to another status when the actor may (OrderStatus::nextFor, roadmap D3.5), sets the matching time
 * and writes order_status_logs. The only way an order's status changes.
 */
final class ChangeOrderStatus
{
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
        });
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
