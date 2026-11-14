<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Wishlist;

/**
 * Saves a product to the customer's wishlist once (roadmap S18); saving it again changes nothing. Only active
 * products can be saved. Returns whether the product is saved.
 */
final class AddToWishlist
{
    #[\NoDiscard]
    public function __invoke(int $customerId, int $productId): bool
    {
        if (! Product::query()->active()->whereKey($productId)->exists()) {
            return false;
        }

        Wishlist::query()->insertOrIgnore([
            Wishlist::COLUMN_CUSTOMER_ID => $customerId,
            Wishlist::COLUMN_PRODUCT_ID => $productId,
            Wishlist::COLUMN_CREATED_AT => now(),
        ]);

        return true;
    }
}
