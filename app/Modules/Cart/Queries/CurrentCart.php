<?php

declare(strict_types=1);

namespace App\Modules\Cart\Queries;

use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Data\CartSummaryData;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Support\CartCookie;
use App\Modules\Catalog\Queries\PurchasableVariants;
use App\Modules\Settings\Queries\StoreSettings;

/**
 * Finds a shopper's cart and builds what it shows (roadmap D13.3, B9.6, S19): current prices and stock for every line,
 * totals of what can be bought, and the free-shipping progress.
 */
final readonly class CurrentCart
{
    public function __construct(
        private PurchasableVariants $variants,
        private StoreSettings $settings,
    ) {}

    /**
     * The customer's cart, or the guest's (by cookie token hash, while not expired); null when there is none.
     */
    public function find(CartOwnerData $owner): ?Cart
    {
        if ($owner->customerId !== null) {
            return Cart::query()->where(Cart::COLUMN_CUSTOMER_ID, $owner->customerId)->first();
        }

        if ($owner->guestToken === null) {
            return null;
        }

        return Cart::query()
            ->where(Cart::COLUMN_SESSION_TOKEN_HASH, CartCookie::hash($owner->guestToken))
            ->where(Cart::COLUMN_EXPIRES_AT, '>', now())
            ->first();
    }

    public function summary(CartOwnerData $owner): CartSummaryData
    {
        $cart = $this->find($owner);

        return $cart instanceof Cart ? $this->summaryOf($cart) : CartSummaryData::empty($this->settings->freeShippingThreshold());
    }

    /**
     * Just how many of each SKU the shopper has (lightweight, for product cards; S19): reads cart_items only, no
     * prices, products or images.
     *
     * @return array<int, int> variant id => quantity
     */
    public function quantities(CartOwnerData $owner): array
    {
        $cart = $this->find($owner);

        if (! $cart instanceof Cart) {
            return [];
        }

        $quantities = [];

        foreach (CartItem::query()->where(CartItem::COLUMN_CART_ID, $cart->getId())->pluck(CartItem::COLUMN_QUANTITY, CartItem::COLUMN_VARIANT_ID) as $variantId => $quantity) {
            $quantities[(int) $variantId] = (int) $quantity;
        }

        return $quantities;
    }

    /**
     * The Alpine «cart» store's state: quantity and limit per order of each SKU in the cart (so «+» greys out at the
     * limit), item count and total (rial) of what can be bought.
     *
     * @return array{items: array<int, int>, limits: array<int, int>, count: int, total: int}
     */
    public function state(CartOwnerData $owner): array
    {
        $summary = $this->summary($owner);
        $limits = [];

        foreach ($summary->lines as $line) {
            $limits[$line->variant->variantId] = $line->variant->maxPerOrder;
        }

        return ['items' => $summary->quantities(), 'limits' => $limits, 'count' => $summary->itemCount, 'total' => $summary->subtotal];
    }

    public function summaryOf(Cart $cart): CartSummaryData
    {
        $items = $cart->items()->get();
        $variants = $this->variants->find(array_values($items->map(fn (CartItem $item): int => $item->getVariantId())->all()));
        $lines = [];
        $count = 0;
        $subtotal = 0;
        $compareAt = 0;

        foreach ($items as $item) {
            $variant = $variants[$item->getVariantId()] ?? null;

            if ($variant === null) {
                continue;
            }

            $quantity = $item->getQuantity();
            $line = new CartLineData(
                variant: $variant,
                quantity: $quantity,
                lineTotal: $variant->price * $quantity,
                previousPrice: $item->getUnitPriceSnapshot() !== $variant->price ? $item->getUnitPriceSnapshot() : null,
            );
            $lines[] = $line;

            if ($line->isAvailable()) {
                $count += $quantity;
                $subtotal += $line->lineTotal;
                $compareAt += ($variant->compareAtPrice ?? $variant->price) * $quantity;
            }
        }

        return new CartSummaryData($lines, $count, $subtotal, $compareAt, $this->settings->freeShippingThreshold());
    }
}
