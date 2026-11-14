<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

/**
 * The product a guest wanted to save before signing in (roadmap S18), kept in the session until they sign in.
 */
final class PendingWishlist
{
    private const string SESSION_KEY = 'wishlist.pending_product_id';

    public const string SAVED_FLASH = 'wishlist.pending_saved';

    public static function remember(int $productId): void
    {
        session()->put(self::SESSION_KEY, $productId);
    }

    public static function pull(): ?int
    {
        $productId = session()->pull(self::SESSION_KEY);

        return is_numeric($productId) ? (int) $productId : null;
    }
}
