<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Cart\Database\Factories\CouponFactory;
use App\Modules\Cart\Enums\CouponType;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A discount code (roadmap D3.4, S20). Money is rial; a percent coupon's value is 1–100. The include lists hold
 * catalog ids only (categories cover their subcategories; a product covers all its SKUs); with no list the coupon
 * covers the whole cart. Haraj (sale-priced) items are covered only when applies_to_sale_items is on.
 */
final class Coupon extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_CODE = 'code';

    public const string COLUMN_TYPE = 'type';

    public const string COLUMN_VALUE = 'value';

    public const string COLUMN_MAX_DISCOUNT = 'max_discount';

    public const string COLUMN_MIN_ORDER_AMOUNT = 'min_order_amount';

    public const string COLUMN_APPLIES_TO_SALE_ITEMS = 'applies_to_sale_items';

    public const string COLUMN_STARTS_AT = 'starts_at';

    public const string COLUMN_ENDS_AT = 'ends_at';

    public const string COLUMN_USAGE_LIMIT_TOTAL = 'usage_limit_total';

    public const string COLUMN_USAGE_LIMIT_PER_CUSTOMER = 'usage_limit_per_customer';

    public const string COLUMN_FIRST_ORDER_ONLY = 'first_order_only';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    /** Include list pivot table => its catalog id column. */
    public const array INCLUDE_LISTS = [
        'coupon_categories' => 'category_id',
        'coupon_brands' => 'brand_id',
        'coupon_products' => 'product_id',
    ];

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCode(): string
    {
        return (string) $this->getAttribute(self::COLUMN_CODE);
    }

    public function setCode(string $code): static
    {
        return $this->setAttribute(self::COLUMN_CODE, self::normalizeCode($code));
    }

    public function getType(): CouponType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function setType(CouponType $type): static
    {
        return $this->setAttribute(self::COLUMN_TYPE, $type);
    }

    public function getValue(): int
    {
        return (int) $this->getAttribute(self::COLUMN_VALUE);
    }

    public function setValue(int $value): static
    {
        return $this->setAttribute(self::COLUMN_VALUE, $value);
    }

    public function getMaxDiscount(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_MAX_DISCOUNT);

        return $value === null ? null : (int) $value;
    }

    public function setMaxDiscount(?int $maxDiscount): static
    {
        return $this->setAttribute(self::COLUMN_MAX_DISCOUNT, $maxDiscount);
    }

    public function getMinOrderAmount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_MIN_ORDER_AMOUNT);
    }

    public function setMinOrderAmount(int $minOrderAmount): static
    {
        return $this->setAttribute(self::COLUMN_MIN_ORDER_AMOUNT, $minOrderAmount);
    }

    public function appliesToSaleItems(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_APPLIES_TO_SALE_ITEMS);
    }

    public function setAppliesToSaleItems(bool $appliesToSaleItems): static
    {
        return $this->setAttribute(self::COLUMN_APPLIES_TO_SALE_ITEMS, $appliesToSaleItems);
    }

    public function getStartsAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_STARTS_AT);
    }

    public function setStartsAt(?Carbon $startsAt): static
    {
        return $this->setAttribute(self::COLUMN_STARTS_AT, $startsAt);
    }

    public function getEndsAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_ENDS_AT);
    }

    public function setEndsAt(?Carbon $endsAt): static
    {
        return $this->setAttribute(self::COLUMN_ENDS_AT, $endsAt);
    }

    public function getUsageLimitTotal(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_USAGE_LIMIT_TOTAL);

        return $value === null ? null : (int) $value;
    }

    public function setUsageLimitTotal(?int $usageLimitTotal): static
    {
        return $this->setAttribute(self::COLUMN_USAGE_LIMIT_TOTAL, $usageLimitTotal);
    }

    public function getUsageLimitPerCustomer(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_USAGE_LIMIT_PER_CUSTOMER);

        return $value === null ? null : (int) $value;
    }

    public function setUsageLimitPerCustomer(?int $usageLimitPerCustomer): static
    {
        return $this->setAttribute(self::COLUMN_USAGE_LIMIT_PER_CUSTOMER, $usageLimitPerCustomer);
    }

    public function isFirstOrderOnly(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_FIRST_ORDER_ONLY);
    }

    public function setFirstOrderOnly(bool $firstOrderOnly): static
    {
        return $this->setAttribute(self::COLUMN_FIRST_ORDER_ONLY, $firstOrderOnly);
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $isActive);
    }

    /**
     * Codes are compared case-insensitively and typed with Persian or Latin digits: stored upper-case, Latin digits.
     */
    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', PersianNormalizer::digits(trim($code))) ?? '');
    }

    /**
     * Catalog ids in one include list (Coupon::INCLUDE_LISTS key).
     *
     * @return list<int>
     */
    public function includedIds(string $table): array
    {
        if (! $this->exists) {
            return [];
        }

        return array_values(array_map(
            intval(...),
            DB::table($table)->where('coupon_id', $this->getId())->pluck(self::INCLUDE_LISTS[$table])->all(),
        ));
    }

    /**
     * @return HasMany<CouponUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, CouponUsage::COLUMN_COUPON_ID);
    }

    public function activityLogLabel(): string
    {
        return 'کوپن: '.$this->getCode();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cart')
            ->logOnly([
                self::COLUMN_CODE, self::COLUMN_TYPE, self::COLUMN_VALUE, self::COLUMN_MAX_DISCOUNT, self::COLUMN_MIN_ORDER_AMOUNT,
                self::COLUMN_APPLIES_TO_SALE_ITEMS, self::COLUMN_STARTS_AT, self::COLUMN_ENDS_AT, self::COLUMN_USAGE_LIMIT_TOTAL,
                self::COLUMN_USAGE_LIMIT_PER_CUSTOMER, self::COLUMN_FIRST_ORDER_ONLY, self::COLUMN_IS_ACTIVE,
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_TYPE => CouponType::class,
            self::COLUMN_VALUE => 'integer',
            self::COLUMN_MAX_DISCOUNT => 'integer',
            self::COLUMN_MIN_ORDER_AMOUNT => 'integer',
            self::COLUMN_APPLIES_TO_SALE_ITEMS => 'boolean',
            self::COLUMN_STARTS_AT => 'datetime',
            self::COLUMN_ENDS_AT => 'datetime',
            self::COLUMN_USAGE_LIMIT_TOTAL => 'integer',
            self::COLUMN_USAGE_LIMIT_PER_CUSTOMER => 'integer',
            self::COLUMN_FIRST_ORDER_ONLY => 'boolean',
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
