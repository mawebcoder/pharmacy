<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Requests;

use App\Modules\Cart\Data\CouponData;
use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for a coupon (roadmap C5, S22); the Filament form applies the same baseRules(). Codes are unique
 * (deleted coupons included), letters A–Z, digits and «-».
 */
final class SaveCouponRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public static function baseRules(?int $couponId = null, ?CouponType $type = null): array
    {
        return [
            Coupon::COLUMN_CODE => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9\-]+$/', Rule::unique(Coupon::class, Coupon::COLUMN_CODE)->ignore($couponId)],
            Coupon::COLUMN_TYPE => ['required', Rule::enum(CouponType::class)],
            Coupon::COLUMN_VALUE => match ($type) {
                CouponType::FreeShipping => ['nullable'],
                CouponType::Percent => ['required', 'integer', 'min:1', 'max:100'],
                default => ['required', 'integer', 'min:1'],
            },
            Coupon::COLUMN_MAX_DISCOUNT => ['nullable', 'integer', 'min:10'],
            Coupon::COLUMN_MIN_ORDER_AMOUNT => ['nullable', 'integer', 'min:0'],
            Coupon::COLUMN_APPLIES_TO_SALE_ITEMS => ['boolean'],
            Coupon::COLUMN_STARTS_AT => ['nullable', 'date'],
            Coupon::COLUMN_ENDS_AT => ['nullable', 'date', 'after:'.Coupon::COLUMN_STARTS_AT],
            Coupon::COLUMN_USAGE_LIMIT_TOTAL => ['nullable', 'integer', 'min:1'],
            Coupon::COLUMN_USAGE_LIMIT_PER_CUSTOMER => ['nullable', 'integer', 'min:1'],
            Coupon::COLUMN_FIRST_ORDER_ONLY => ['boolean'],
            Coupon::COLUMN_IS_ACTIVE => ['boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'brand_ids' => ['array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    public function authorize(): bool
    {
        $coupon = $this->route('coupon');

        return $coupon instanceof Coupon
            ? ($this->user('admin')?->can('update', $coupon) ?? false)
            : ($this->user('admin')?->can('create', Coupon::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return self::baseRules($coupon instanceof Coupon ? $coupon->getId() : null, CouponType::tryFrom((string) $this->input(Coupon::COLUMN_TYPE)));
    }

    public function toDto(): CouponData
    {
        return CouponData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([Coupon::COLUMN_CODE => Coupon::normalizeCode((string) $this->input(Coupon::COLUMN_CODE))]);
    }
}
