<?php

declare(strict_types=1);

namespace App\Modules\Cart\Database\Factories;

use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Active 10% coupons with no limits. States: percent(), fixed(), freeShipping(), inactive().
 *
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Coupon::COLUMN_CODE => 'TEST'.fake()->unique()->numberBetween(1000, 999_999),
            Coupon::COLUMN_TYPE => CouponType::Percent,
            Coupon::COLUMN_VALUE => 10,
            Coupon::COLUMN_MIN_ORDER_AMOUNT => 0,
            Coupon::COLUMN_APPLIES_TO_SALE_ITEMS => false,
            Coupon::COLUMN_FIRST_ORDER_ONLY => false,
            Coupon::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function percent(int $percent, ?int $maxDiscount = null): static
    {
        return $this->state(fn (array $attributes): array => [Coupon::COLUMN_TYPE => CouponType::Percent, Coupon::COLUMN_VALUE => $percent, Coupon::COLUMN_MAX_DISCOUNT => $maxDiscount]);
    }

    public function fixed(int $rial): static
    {
        return $this->state(fn (array $attributes): array => [Coupon::COLUMN_TYPE => CouponType::Fixed, Coupon::COLUMN_VALUE => $rial]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes): array => [Coupon::COLUMN_TYPE => CouponType::FreeShipping, Coupon::COLUMN_VALUE => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Coupon::COLUMN_IS_ACTIVE => false]);
    }
}
