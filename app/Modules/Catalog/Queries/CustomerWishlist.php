<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Wishlist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A customer's saved products (roadmap D3.3, S18), for the account pages and the heart buttons.
 */
final readonly class CustomerWishlist
{
    /**
     * Saved product ids, newest first, so the heart buttons of a page can show their state.
     *
     * @return list<int>
     */
    public function productIds(int $customerId): array
    {
        return array_values(Wishlist::query()
            ->where(Wishlist::COLUMN_CUSTOMER_ID, $customerId)
            ->latest(Wishlist::COLUMN_ID)
            ->pluck(Wishlist::COLUMN_PRODUCT_ID)
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    public function count(int $customerId): int
    {
        return Wishlist::query()->where(Wishlist::COLUMN_CUSTOMER_ID, $customerId)->count();
    }

    /**
     * Saved products that are still on the shop (active), newest saved first, loaded for product cards.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(int $customerId, int $perPage = 24): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->with(ProductListing::CARD_RELATIONS)
            ->join('wishlists', 'wishlists.'.Wishlist::COLUMN_PRODUCT_ID, '=', 'products.id')
            ->where('wishlists.'.Wishlist::COLUMN_CUSTOMER_ID, $customerId)
            ->orderByDesc('wishlists.'.Wishlist::COLUMN_ID)
            ->select('products.*')
            ->paginate($perPage);
    }
}
