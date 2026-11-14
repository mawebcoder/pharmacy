<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\CartSummaryData;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;

/**
 * After the cart page has shown «قیمت این کالا تغییر کرد», the lines' prices are taken as seen, so the notice shows once.
 */
final class AcknowledgeCartPrices
{
    public function __invoke(Cart $cart, CartSummaryData $summary): void
    {
        foreach ($summary->lines as $line) {
            if ($line->previousPrice !== null) {
                CartItem::query()
                    ->where(CartItem::COLUMN_CART_ID, $cart->getId())
                    ->where(CartItem::COLUMN_VARIANT_ID, $line->variant->variantId)
                    ->update([CartItem::COLUMN_UNIT_PRICE_SNAPSHOT => $line->variant->price]);
            }
        }
    }
}
