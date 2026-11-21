<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Enums\CustomerOrderTab;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A customer's own orders for the account area (roadmap B9.9; read by the Profile module since 2026-11-18).
 */
final readonly class CustomerOrders
{
    /**
     * Newest first, with their items and returns, a page at a time; only the tab's orders (owner, 2026-11-19).
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(int $customerId, int $perPage = 10, CustomerOrderTab $tab = CustomerOrderTab::All): LengthAwarePaginator
    {
        return $this->inTab(Order::query()->ownedBy($customerId), $tab)
            ->with(['items', 'returns.items'])
            ->latest(Order::COLUMN_ID)
            ->paginate($perPage);
    }

    /**
     * How many orders each tab holds.
     *
     * @return array<string, int> tab value => count
     */
    public function tabCounts(int $customerId): array
    {
        $counts = [];

        foreach (CustomerOrderTab::cases() as $tab) {
            $counts[$tab->value] = $this->inTab(Order::query()->ownedBy($customerId), $tab)->count();
        }

        return $counts;
    }

    public function count(int $customerId): int
    {
        return Order::query()->ownedBy($customerId)->count();
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function inTab(Builder $query, CustomerOrderTab $tab): Builder
    {
        if ($tab === CustomerOrderTab::Returned) {
            return $query->whereHas('returns', fn (Builder $returns) => $returns->where(OrderReturn::COLUMN_STATUS, '!=', OrderReturnStatus::Cancelled));
        }

        $statuses = $tab->statuses();

        return $statuses === null ? $query : $query->whereIn(Order::COLUMN_STATUS, $statuses);
    }
}
