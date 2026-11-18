<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;

/**
 * Empties a cart once its order is placed (roadmap S23, owner 2026-11-14: the order keeps its own copy) or when the
 * customer taps «حذف همه» (owner, 2026-11-15), and forgets the picked address.
 */
final class EmptyCart
{
    public function __invoke(int $cartId): void
    {
        CartItem::query()->where(CartItem::COLUMN_CART_ID, $cartId)->delete();
        Cart::query()->whereKey($cartId)->update([Cart::COLUMN_ADDRESS_ID => null]);
    }
}
