<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use App\Modules\Cart\Data\CartLineData;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Orders\Enums\OrderSource;

/**
 * An order the Checkout module has checked and wants created (roadmap D13.4; split from PlaceOrder 2026-11-18): who,
 * where, the copies the order keeps, the money (rial) and the cart lines with each one's share of the coupon discount.
 */
final readonly class NewOrderData
{
    /**
     * @param  array<string, mixed>  $customerSnapshot
     * @param  array<string, mixed>|null  $couponSnapshot
     * @param  array<string, mixed>  $shippingQuoteSnapshot
     * @param  list<CartLineData>  $lines
     * @param  array<int, int>  $discounts  variant id => discount share (the coupon's, or staff's manual discount)
     */
    public function __construct(
        public int $customerId,
        public AddressSnapshotData $address,
        public array $customerSnapshot,
        public int $subtotal,
        public int $shippingPrice,
        public int $shippingDiscount,
        public ?string $shippingDiscountReason,
        public int $shippingTotal,
        public ?int $couponId,
        public ?string $couponCode,
        public ?array $couponSnapshot,
        public string $shippingMethod,
        public array $shippingQuoteSnapshot,
        public ?string $customerNote,
        public array $lines,
        public array $discounts,
        public OrderSource $source = OrderSource::Site,
        public ?int $createdByAdminId = null,
        public int $manualDiscount = 0,
        public ?string $manualDiscountReason = null,
    ) {}

    public function discountTotal(): int
    {
        return array_sum($this->discounts);
    }

    public function total(): int
    {
        return $this->subtotal - $this->discountTotal() + $this->shippingTotal;
    }
}
