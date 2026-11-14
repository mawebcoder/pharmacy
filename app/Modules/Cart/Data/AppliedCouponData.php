<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

/**
 * The coupon on a cart as checked now (roadmap S20): its discount (rial, whole toman) and whether it makes shipping
 * free, or, when it no longer qualifies, why (it then gives nothing).
 */
final readonly class AppliedCouponData
{
    /**
     * @param  list<int>  $coveredVariantIds  the SKUs the discount applies to (an order shares it out over them)
     */
    public function __construct(
        public int $couponId,
        public string $code,
        public int $discount,
        public bool $freeShipping,
        public int $coveredSubtotal,
        public ?string $problem = null,
        public ?string $problemCode = null,
        public array $coveredVariantIds = [],
    ) {}

    public static function refused(int $couponId, string $code, string $problem, string $problemCode): self
    {
        return new self($couponId, $code, 0, false, 0, $problem, $problemCode);
    }

    public function isValid(): bool
    {
        return $this->problem === null;
    }
}
