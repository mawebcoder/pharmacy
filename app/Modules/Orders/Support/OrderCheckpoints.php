<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Data\OrderCheckpointData;
use App\Modules\Orders\Enums\CheckpointState;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusLog;
use Illuminate\Support\Carbon;

/**
 * The checkpoints of an order's tracker (owner, 2026-11-16): paid → processing → on the way → delivered. Checkpoints
 * before the order's status are passed, its status is current (delivered counts as passed: nothing is left to wait
 * for) and the rest are upcoming. A cancelled order keeps the checkpoints it passed and ends on «لغو شد»; an expired
 * one never started, so it has none. Reads the order's loaded statusLogs for the times.
 */
final class OrderCheckpoints
{
    /**
     * @var list<OrderStatus>
     */
    public const array FLOW = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::OnTheWay, OrderStatus::Delivered];

    /**
     * @return list<OrderCheckpointData>
     */
    public static function for(Order $order): array
    {
        $status = $order->getStatus();

        return match ($status) {
            OrderStatus::Expired => [],
            OrderStatus::Pending => array_map(fn (OrderStatus $step): OrderCheckpointData => new OrderCheckpointData($step, CheckpointState::Upcoming), self::FLOW),
            OrderStatus::Cancelled => self::cancelled($order),
            default => self::inFlow($order, $status),
        };
    }

    /**
     * @return list<OrderCheckpointData>
     */
    private static function inFlow(Order $order, OrderStatus $status): array
    {
        $position = array_search($status, self::FLOW, true);
        $checkpoints = [];

        foreach (self::FLOW as $index => $step) {
            $state = match (true) {
                $index < $position, $step === OrderStatus::Delivered && $index === $position => CheckpointState::Passed,
                $index === $position => CheckpointState::Current,
                default => CheckpointState::Upcoming,
            };

            $checkpoints[] = new OrderCheckpointData($step, $state, $state === CheckpointState::Upcoming ? null : self::reachedAt($order, $step));
        }

        return $checkpoints;
    }

    /**
     * The checkpoints passed up to the status it was cancelled from, then the cancellation itself.
     *
     * @return list<OrderCheckpointData>
     */
    private static function cancelled(Order $order): array
    {
        $cancelLog = $order->statusLogs->last(fn (OrderStatusLog $log): bool => $log->getToStatus() === OrderStatus::Cancelled);
        $position = array_search($cancelLog?->getFromStatus(), self::FLOW, true);
        $checkpoints = [];

        foreach (self::FLOW as $index => $step) {
            $passed = $position === false ? self::reachedAt($order, $step) !== null : $index <= $position;

            if ($passed) {
                $checkpoints[] = new OrderCheckpointData($step, CheckpointState::Passed, self::reachedAt($order, $step));
            }
        }

        $checkpoints[] = new OrderCheckpointData(OrderStatus::Cancelled, CheckpointState::Current, $cancelLog?->getCreatedAt() ?? $order->getCancelledAt());

        return $checkpoints;
    }

    private static function reachedAt(Order $order, OrderStatus $step): ?Carbon
    {
        $log = $order->statusLogs->last(fn (OrderStatusLog $log): bool => $log->getToStatus() === $step);

        return $log?->getCreatedAt() ?? ($step === OrderStatus::Paid ? $order->getPaidAt() : null);
    }
}
