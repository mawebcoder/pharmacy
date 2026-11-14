<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Exceptions\CartRefused;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Cart\Support\CartCookie;
use App\Modules\Catalog\Queries\PurchasableVariants;
use Illuminate\Support\Facades\DB;

/**
 * Adds units of a SKU to the shopper's cart (roadmap D13.3, S19), creating the cart (and, for a guest, the cookie)
 * on the first add. Refuses SKUs that cannot be bought (inactive, out of stock, prescription, cold chain) and caps
 * at the limit per order (SKU, else product, else the store setting). Returns the SKU's new quantity.
 */
final readonly class AddToCart
{
    public function __construct(
        private CurrentCart $carts,
        private PurchasableVariants $variants,
    ) {}

    /**
     * @throws CartRefused
     */
    #[\NoDiscard]
    public function __invoke(CartOwnerData $owner, int $variantId, int $quantity = 1): int
    {
        $variant = $this->variants->one($variantId) ?? throw CartRefused::cannotBuy('این کالا پیدا نشد.');

        if (! $variant->canBuy()) {
            throw CartRefused::cannotBuy((string) $variant->refusalMessage());
        }

        return DB::transaction(function () use ($owner, $variant, $quantity): int {
            $cart = $this->carts->find($owner) ?? $this->create($owner);
            $item = $cart->items()->where(CartItem::COLUMN_VARIANT_ID, $variant->variantId)->lockForUpdate()->first();
            $current = $item?->getQuantity() ?? 0;

            if ($current >= $variant->maxPerOrder) {
                throw CartRefused::limitReached($variant->maxPerOrder);
            }

            $new = min($variant->maxPerOrder, $current + max(1, $quantity));
            ($item ?? (new CartItem)->setCartId($cart->getId())->setVariantId($variant->variantId))
                ->setQuantity($new)
                ->setUnitPriceSnapshot($variant->price)
                ->save();

            if ($owner->isGuest()) {
                $cart->setExpiresAt(now()->addDays(CartCookie::LIFETIME_DAYS))->save();
            }

            return $new;
        });
    }

    private function create(CartOwnerData $owner): Cart
    {
        $cart = new Cart;

        if ($owner->customerId !== null) {
            $cart->setCustomerId($owner->customerId);
        } else {
            $cart->setSessionTokenHash(CartCookie::hash($owner->guestToken ?? CartCookie::issue()))
                ->setExpiresAt(now()->addDays(CartCookie::LIFETIME_DAYS));
        }

        $cart->save();

        return $cart;
    }
}
