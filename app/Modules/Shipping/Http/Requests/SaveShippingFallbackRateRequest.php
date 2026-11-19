<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Data\ShippingFallbackRateData;
use App\Modules\Shipping\Models\ShippingFallbackRate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for «نرخ ارسال جایگزین» (roadmap S31), shared with the admin form: a weight up to 100 kg and prices above
 * zero (rial). The weight is unique; the admin form adds that rule with the row being edited ignored.
 */
final class SaveShippingFallbackRateRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        $price = ['required', 'integer', 'min:1000', 'max:1000000000'];

        return [
            ShippingFallbackRate::COLUMN_MAX_WEIGHT_G => ['required', 'integer', 'min:1', 'max:100000'],
            ShippingFallbackRate::COLUMN_SAME_CITY_PRICE => $price,
            ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE => $price,
            ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE => $price,
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Create:ShippingFallbackRate') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = self::baseRules();
        $rules[ShippingFallbackRate::COLUMN_MAX_WEIGHT_G][] = 'unique:shipping_fallback_rates,'.ShippingFallbackRate::COLUMN_MAX_WEIGHT_G;

        return $rules;
    }

    public function toDto(): ShippingFallbackRateData
    {
        return ShippingFallbackRateData::fromArray($this->validated());
    }
}
