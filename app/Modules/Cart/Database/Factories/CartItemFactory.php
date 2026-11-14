<?php

declare(strict_types=1);

namespace App\Modules\Cart\Database\Factories;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
final class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * Pass variant_id (a Catalog SKU).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            CartItem::COLUMN_CART_ID => Cart::factory(),
            CartItem::COLUMN_QUANTITY => 1,
            CartItem::COLUMN_UNIT_PRICE_SNAPSHOT => 1_000_000,
        ];
    }
}
