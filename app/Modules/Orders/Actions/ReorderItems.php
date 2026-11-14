<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Cart\Actions\AddToCart;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Exceptions\CartRefused;
use App\Modules\Cart\Queries\CurrentCart;
use App\Modules\Catalog\Data\PurchasableVariantData;
use App\Modules\Catalog\Queries\PurchasableVariants;
use App\Modules\Orders\Data\ReorderResultData;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

/**
 * «سفارش دوباره» (owner, 2026-11-14): the order's items that can be bought now go back into the customer's cart at
 * today's price, added to what is already there and capped by each item's limit per order; the rest are skipped and
 * reported.
 */
final readonly class ReorderItems
{
    public function __construct(
        private PurchasableVariants $variants,
        private CurrentCart $carts,
        private AddToCart $addToCart,
    ) {}

    /**
     * @throws OrderRefused
     */
    #[\NoDiscard]
    public function __invoke(Order $order, int $customerId): ReorderResultData
    {
        if ($order->getCustomerId() !== $customerId) {
            throw OrderRefused::notYours();
        }

        $owner = new CartOwnerData($customerId, null);
        $items = $order->items()->get();
        $now = $this->variants->find(array_values(array_filter($items->map(fn (OrderItem $item): ?int => $item->getVariantId())->all())));
        $inCart = $this->carts->quantities($owner);
        $added = 0;
        $skipped = [];

        foreach ($items as $item) {
            $variant = $item->getVariantId() === null ? null : ($now[$item->getVariantId()] ?? null);

            if (! $variant instanceof PurchasableVariantData || ! $variant->canBuy()) {
                $skipped[] = $item->getProductName();

                continue;
            }

            $current = $inCart[$variant->variantId] ?? 0;
            $toAdd = min($variant->maxPerOrder, $current + $item->getQuantity()) - $current;

            if ($toAdd <= 0) {
                $added++;

                continue;
            }

            try {
                $inCart[$variant->variantId] = ($this->addToCart)($owner, $variant->variantId, $toAdd);
                $added++;
            } catch (CartRefused) {
                $skipped[] = $item->getProductName();
            }
        }

        if ($added === 0) {
            throw OrderRefused::nothingToReorder();
        }

        return new ReorderResultData($added, $skipped);
    }
}
