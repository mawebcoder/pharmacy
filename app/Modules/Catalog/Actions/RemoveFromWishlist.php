<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Wishlist;

/**
 * Removes a product from the customer's wishlist (roadmap S18).
 */
final class RemoveFromWishlist
{
    public function __invoke(int $customerId, int $productId): void
    {
        Wishlist::query()
            ->where(Wishlist::COLUMN_CUSTOMER_ID, $customerId)
            ->where(Wishlist::COLUMN_PRODUCT_ID, $productId)
            ->delete();
    }
}
