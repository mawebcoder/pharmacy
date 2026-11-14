<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Support\CartCookie;
use App\Modules\Catalog\Queries\PurchasableVariants;
use Illuminate\Support\Facades\DB;

/**
 * When a guest signs in (roadmap D13.3, owner 2026-11-14, S19): without a cart of their own, the guest cart becomes
 * theirs and its token hash is cleared; with one, the guest lines are added to it (same SKU: quantities added and
 * capped at the limit) and the guest cart is deleted. The cookie is removed either way.
 */
final readonly class MergeGuestCart
{
    public function __construct(private PurchasableVariants $variants) {}

    public function __invoke(string $guestToken, int $customerId): void
    {
        DB::transaction(function () use ($guestToken, $customerId): void {
            $guest = Cart::query()->where(Cart::COLUMN_SESSION_TOKEN_HASH, CartCookie::hash($guestToken))->lockForUpdate()->first();

            if ($guest === null) {
                return;
            }

            $own = Cart::query()->where(Cart::COLUMN_CUSTOMER_ID, $customerId)->lockForUpdate()->first();

            if ($own === null) {
                $guest->setCustomerId($customerId)->setSessionTokenHash(null)->setExpiresAt(null)->save();

                return;
            }

            $guestItems = $guest->items()->get();
            $limits = $this->variants->find(array_values($guestItems->map(fn (CartItem $item): int => $item->getVariantId())->all()));

            foreach ($guestItems as $guestItem) {
                $max = $limits[$guestItem->getVariantId()]->maxPerOrder ?? $guestItem->getQuantity();
                $ownItem = $own->items()->where(CartItem::COLUMN_VARIANT_ID, $guestItem->getVariantId())->first()
                    ?? (new CartItem)->setCartId($own->getId())->setVariantId($guestItem->getVariantId())->setQuantity(0);

                $ownItem->setQuantity(min($max, $ownItem->getQuantity() + $guestItem->getQuantity()))
                    ->setUnitPriceSnapshot($guestItem->getUnitPriceSnapshot())
                    ->save();
            }

            $guest->delete();
        });

        CartCookie::forget();
    }
}
