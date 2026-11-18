<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

/**
 * The cart as shown on /cart and in the mini-cart (roadmap B9.6, S21). Amounts in rial; only lines that can be bought
 * count. The free-shipping threshold comes from «تنظیمات فروشگاه» (0 means no offer). Checkout (S23) adds the coupon
 * it worked out on the fly (withCoupon); the cart page shows none.
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
        public ?AppliedCouponData $coupon = null,
    ) {}

    public function withCoupon(?AppliedCouponData $coupon): self
    {
        return new self($this->lines, $this->itemCount, $this->subtotal, $this->compareAtTotal, $this->freeShippingThreshold, $coupon);
    }

    /**
     * The coupon's discount (rial), 0 when none applies.
     */
    public function discount(): int
    {
        return $this->coupon instanceof AppliedCouponData ? $this->coupon->discount : 0;
    }

    /**
     * What the customer pays for the items, after the coupon (shipping comes at checkout).
     */
    public function total(): int
    {
        return max(0, $this->subtotal - $this->discount());
    }

    /**
     * Shipping is free through the store's threshold or a valid free-shipping coupon.
     */
    public function shippingIsFree(): bool
    {
        return $this->hasFreeShipping() || ($this->coupon instanceof AppliedCouponData && $this->coupon->freeShipping);
    }

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
