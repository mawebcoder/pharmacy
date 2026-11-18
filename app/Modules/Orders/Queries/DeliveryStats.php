<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

/**
 * What the store has delivered, for other modules (roadmap S30 trust counters): delivered orders and the cities they
 * went to (from each order's address snapshot).
 */
final readonly class DeliveryStats
{
    public function deliveredOrders(): int
    {
        return Order::query()->where(Order::COLUMN_STATUS, OrderStatus::Delivered)->count();
    }

    public function citiesDeliveredTo(): int
    {
        return (int) Order::query()
            ->where(Order::COLUMN_STATUS, OrderStatus::Delivered)
            ->selectRaw("count(distinct json_unquote(json_extract(address_snapshot, '$.city_id'))) as cities")
            ->value('cities');
    }
}
