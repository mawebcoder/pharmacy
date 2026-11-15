<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * Pending orders of 1,000,000 rial that expire in 30 minutes. States: paid(), expired().
 *
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Orders must not use Identity's models (C3 rule 1): a bare customer row unless one is given.
            Order::COLUMN_CUSTOMER_ID => fn (): int => DB::table('customers')->insertGetId(['mobile' => '0912'.fake()->unique()->numerify('#######'), 'created_at' => now(), 'updated_at' => now()]),
            Order::COLUMN_STATUS => OrderStatus::Pending,
            Order::COLUMN_PAYMENT_STATUS => PaymentStatus::Pending,
            Order::COLUMN_ADDRESS_SNAPSHOT => ['title' => 'خانه', 'province' => 'تهران', 'city' => 'تهران', 'main_street' => 'ولیعصر', 'plaque' => '12', 'postal_code' => '1234567890', 'receiver_name' => 'سارا رضایی', 'receiver_mobile' => '09123456789'],
            Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'سارا رضایی', 'mobile' => '09123456789'],
            Order::COLUMN_SUBTOTAL => 1_000_000,
            Order::COLUMN_TOTAL => 1_000_000,
            Order::COLUMN_SHIPPING_METHOD => 'flat',
            Order::COLUMN_SHIPPING_QUOTE_SNAPSHOT => ['label' => 'ارسال', 'price' => 0],
            Order::COLUMN_PLACED_AT => now(),
            Order::COLUMN_EXPIRES_AT => now()->addMinutes(30),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            Order::COLUMN_STATUS => OrderStatus::Paid,
            Order::COLUMN_PAYMENT_STATUS => PaymentStatus::Paid,
            Order::COLUMN_PAID_AT => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            Order::COLUMN_STATUS => OrderStatus::Expired,
            Order::COLUMN_PAYMENT_STATUS => PaymentStatus::Expired,
            Order::COLUMN_EXPIRES_AT => now()->subMinute(),
        ]);
    }
}
