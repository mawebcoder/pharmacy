<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use App\Modules\Orders\Enums\CheckpointState;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Support\Carbon;

/**
 * One checkpoint of an order's tracker: the status, how far the order is against it and when it got there.
 */
final readonly class OrderCheckpointData
{
    public function __construct(
        public OrderStatus $status,
        public CheckpointState $state,
        public ?Carbon $reachedAt = null,
    ) {}

    public function isPassed(): bool
    {
        return $this->state === CheckpointState::Passed;
    }

    public function isCurrent(): bool
    {
        return $this->state === CheckpointState::Current;
    }
}
