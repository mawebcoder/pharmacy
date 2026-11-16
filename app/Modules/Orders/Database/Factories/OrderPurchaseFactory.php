<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Empty purchase documents (draft, no rows) of a paid order; rows are added with SavePurchaseItem.
 *
 * @extends Factory<OrderPurchase>
 */
final class OrderPurchaseFactory extends Factory
{
    protected $model = OrderPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            OrderPurchase::COLUMN_ORDER_ID => fn (): int => Order::factory()->paid()->create()->getId(),
            OrderPurchase::COLUMN_STATUS => PurchaseStatus::Draft,
        ];
    }
}
