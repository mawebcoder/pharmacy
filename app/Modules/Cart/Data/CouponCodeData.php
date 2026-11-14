<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Modules\Cart\Models\Coupon;

/**
 * A coupon code a signed-in customer typed at checkout, with their cart (roadmap C5.3, S20); the code is normalised.
 */
final readonly class CouponCodeData
{
    public function __construct(
        public int $cartId,
        public string $code,
        public int $customerId,
    ) {}

    /**
     * @param  array{cart_id: int|string, code: string, customer_id: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cartId: (int) $data['cart_id'],
            code: Coupon::normalizeCode($data['code']),
            customerId: (int) $data['customer_id'],
        );
    }
}
