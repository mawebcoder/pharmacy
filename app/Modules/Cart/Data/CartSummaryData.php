<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

/**
 * The cart as shown on /cart and in the mini-cart (roadmap B9.6, S19). Amounts in rial; only lines that can be bought
 * count. The free-shipping threshold comes from «تنظیمات فروشگاه» (0 means no offer).
 */
final readonly class CartSummaryData
{
    /**
     * @param  list<CartLineData>  $lines
     */
    public function __construct(
        public array $lines,
        public int $itemCount,
        public int $subtotal,
        public int $compareAtTotal,
        public int $freeShippingThreshold,
    ) {}

    public static function empty(int $freeShippingThreshold): self
    {
        return new self([], 0, 0, 0, $freeShippingThreshold);
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * What the customer saves against the struck-through prices.
     */
    public function savings(): int
    {
        return max(0, $this->compareAtTotal - $this->subtotal);
    }

    public function remainingForFreeShipping(): int
    {
        return $this->freeShippingThreshold === 0 ? 0 : max(0, $this->freeShippingThreshold - $this->subtotal);
    }

    public function hasFreeShipping(): bool
    {
        return $this->freeShippingThreshold > 0 && $this->subtotal >= $this->freeShippingThreshold;
    }

    /**
     * 0–100, for the progress bar.
     */
    public function freeShippingProgress(): int
    {
        return $this->freeShippingThreshold === 0 ? 0 : (int) min(100, floor($this->subtotal * 100 / $this->freeShippingThreshold));
    }

    public function hasUnavailable(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quantities by SKU, for the add-to-cart buttons on the page.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $quantities[$line->variant->variantId] = $line->quantity;
        }

        return $quantities;
    }
}
