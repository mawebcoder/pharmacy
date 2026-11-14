<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Exceptions\CartRefused;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Catalog\Data\PurchasableVariantData;
use App\Modules\Catalog\Queries\PurchasableVariants;

/**
 * Sets a cart line's quantity (− / + on the stepper, roadmap S19); 0 removes it. Raising is refused for SKUs that
 * cannot be bought now and capped at the limit per order; lowering and removing always work. Returns the quantity.
 */
final readonly class SetCartQuantity
{
    public function __construct(
        private CurrentCart $carts,
        private PurchasableVariants $variants,
    ) {}

    /**
     * @throws CartRefused
     */
    #[\NoDiscard]
    public function __invoke(CartOwnerData $owner, int $variantId, int $quantity): int
    {
        $item = $this->carts->find($owner)?->items()->where(CartItem::COLUMN_VARIANT_ID, $variantId)->first()
            ?? throw CartRefused::notFound();

        if ($quantity <= 0) {
            $item->delete();

            return 0;
        }

        if ($quantity > $item->getQuantity()) {
            $variant = $this->variants->one($variantId);

            if (! $variant instanceof PurchasableVariantData || ! $variant->canBuy()) {
                throw CartRefused::cannotBuy($variant?->refusalMessage() ?? 'این کالا پیدا نشد.');
            }

            if ($item->getQuantity() >= $variant->maxPerOrder) {
                throw CartRefused::limitReached($variant->maxPerOrder);
            }

            $quantity = min($quantity, $variant->maxPerOrder);
        }

        $item->setQuantity($quantity)->save();

        return $quantity;
    }
}
