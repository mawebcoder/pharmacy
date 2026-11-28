<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Carbon\CarbonInterface;

/**
 * Units sold per product, for other modules (roadmap S39 popularity): quantities in orders paid since a moment and not
 * cancelled (paid, processing, on the way or delivered).
 */
final readonly class ProductSales
{
    public const array SOLD = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::OnTheWay, OrderStatus::Delivered];

    /**
     * @return array<int, int> product id => units
     */
    public function unitsSince(CarbonInterface $since): array
    {
        return array_map(intval(...), OrderItem::query()
            ->join('orders', 'orders.'.Order::COLUMN_ID, '=', 'order_items.'.OrderItem::COLUMN_ORDER_ID)
            ->where('orders.'.Order::COLUMN_PAID_AT, '>=', $since)
            ->whereIn('orders.'.Order::COLUMN_STATUS, array_map(fn (OrderStatus $status): string => $status->value, self::SOLD))
            ->groupBy('order_items.'.OrderItem::COLUMN_PRODUCT_ID)
            ->selectRaw('order_items.'.OrderItem::COLUMN_PRODUCT_ID.' as product_id, sum(order_items.'.OrderItem::COLUMN_QUANTITY.') as units')
            ->pluck('units', 'product_id')
            ->all());
    }
}
