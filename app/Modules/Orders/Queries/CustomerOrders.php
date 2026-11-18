<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A customer's own orders for the account area (roadmap B9.9; read by the Profile module since 2026-11-18).
 */
final readonly class CustomerOrders
{
    /**
     * Newest first, with their items, a page at a time.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(int $customerId, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->ownedBy($customerId)
            ->with('items')
            ->latest(Order::COLUMN_ID)
            ->paginate($perPage);
    }

    public function count(int $customerId): int
    {
        return Order::query()->ownedBy($customerId)->count();
    }
}
