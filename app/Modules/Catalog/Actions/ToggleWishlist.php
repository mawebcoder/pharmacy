<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Wishlist;

/**
 * Saves a product to the customer's wishlist, or removes it when it is already saved (roadmap S18, heart button).
 * Only active products can be added. Returns whether the product is saved now.
 */
final class ToggleWishlist
{
    #[\NoDiscard]
    public function __invoke(int $customerId, int $productId): bool
    {
        $removed = Wishlist::query()
            ->where(Wishlist::COLUMN_CUSTOMER_ID, $customerId)
            ->where(Wishlist::COLUMN_PRODUCT_ID, $productId)
            ->delete();

        if ($removed > 0) {
            return false;
        }

        return app(AddToWishlist::class)($customerId, $productId);
    }
}
