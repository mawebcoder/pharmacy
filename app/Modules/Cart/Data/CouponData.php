<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Support\Carbon;

/**
 * Input for creating or updating a coupon (roadmap D3.4, S20). Money in rial; a percent coupon's value is 1–100;
 * the include lists hold catalog ids (products, not SKUs). Times are UTC.
 */
final readonly class CouponData
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $brandIds
     * @param  list<int>  $productIds
     */
    public function __construct(
        public string $code,
        public CouponType $type,
        public int $value,
        public ?int $maxDiscount,
        public int $minOrderAmount,
        public bool $appliesToSaleItems,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
        public ?int $usageLimitTotal,
        public ?int $usageLimitPerCustomer,
        public bool $firstOrderOnly,
        public bool $isActive,
        public array $categoryIds,
        public array $brandIds,
        public array $productIds,
    ) {}

    /**
     * Keys are the coupons columns (Coupon::COLUMN_*) plus «category_ids», «brand_ids», «product_ids».
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data[Coupon::COLUMN_TYPE] ?? CouponType::Percent;
        $type = $type instanceof CouponType ? $type : CouponType::from((string) $type);
        $int = static fn (string $key): ?int => blank($data[$key] ?? null) ? null : (int) $data[$key];
        $time = static fn (string $key): ?Carbon => blank($data[$key] ?? null) ? null : Carbon::parse($data[$key])->utc();
        $ids = static fn (string $key): array => array_values(array_unique(array_map(intval(...), (array) ($data[$key] ?? []))));

        return new self(
            code: Coupon::normalizeCode((string) ($data[Coupon::COLUMN_CODE] ?? '')),
            type: $type,
            value: $type === CouponType::FreeShipping ? 0 : (int) ($data[Coupon::COLUMN_VALUE] ?? 0),
            maxDiscount: $type === CouponType::Percent ? $int(Coupon::COLUMN_MAX_DISCOUNT) : null,
            minOrderAmount: $int(Coupon::COLUMN_MIN_ORDER_AMOUNT) ?? 0,
            appliesToSaleItems: (bool) ($data[Coupon::COLUMN_APPLIES_TO_SALE_ITEMS] ?? false),
            startsAt: $time(Coupon::COLUMN_STARTS_AT),
            endsAt: $time(Coupon::COLUMN_ENDS_AT),
            usageLimitTotal: $int(Coupon::COLUMN_USAGE_LIMIT_TOTAL),
            usageLimitPerCustomer: $int(Coupon::COLUMN_USAGE_LIMIT_PER_CUSTOMER),
            firstOrderOnly: (bool) ($data[Coupon::COLUMN_FIRST_ORDER_ONLY] ?? false),
            isActive: (bool) ($data[Coupon::COLUMN_IS_ACTIVE] ?? true),
            categoryIds: $ids('category_ids'),
            brandIds: $ids('brand_ids'),
            productIds: $ids('product_ids'),
        );
    }
}
