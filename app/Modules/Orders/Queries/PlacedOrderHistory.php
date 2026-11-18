<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Cart\Contracts\CustomerOrderHistory;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

/**
 * «First order only» coupons (S22) ask this: has the customer an order that was paid (and not cancelled)?
 */
final readonly class PlacedOrderHistory implements CustomerOrderHistory
{
    public function hasPlacedOrder(int $customerId): bool
    {
        return Order::query()
            ->ownedBy($customerId)
            ->whereIn(Order::COLUMN_STATUS, array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isPurchase())))
            ->exists();
    }
}
