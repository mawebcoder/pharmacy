<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Queries\CurrentCart;

/**
 * «به‌روزرسانی سبد» (owner, 2026-11-14, S19): removes every line that can no longer be bought (no active supplier,
 * switched off, prescription, cold chain …), so the shopper can continue to checkout. Returns how many were removed.
 */
final readonly class RemoveUnavailableCartItems
{
    public function __construct(private CurrentCart $carts) {}

    #[\NoDiscard]
    public function __invoke(CartOwnerData $owner): int
    {
        $cart = $this->carts->find($owner);

        if (! $cart instanceof Cart) {
            return 0;
        }

        $unavailable = [];

        foreach ($this->carts->summaryOf($cart)->lines as $line) {
            if (! $line->isAvailable()) {
                $unavailable[] = $line->variant->variantId;
            }
        }

        return $unavailable === [] ? 0 : (int) CartItem::query()
            ->where(CartItem::COLUMN_CART_ID, $cart->getId())
            ->whereIn(CartItem::COLUMN_VARIANT_ID, $unavailable)
            ->delete();
    }
}
