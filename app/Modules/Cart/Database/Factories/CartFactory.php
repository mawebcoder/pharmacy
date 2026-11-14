<?php

declare(strict_types=1);

namespace App\Modules\Cart\Database\Factories;

use App\Modules\Cart\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
final class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * A guest cart; pass customer_id for a customer's.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Cart::COLUMN_SESSION_TOKEN_HASH => hash('sha256', fake()->unique()->sha256()),
            Cart::COLUMN_EXPIRES_AT => now()->addDays(30),
        ];
    }
}
