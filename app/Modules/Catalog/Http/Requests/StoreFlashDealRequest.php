<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\FlashDealData;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation for a flash deal (roadmap C5, S33). Price in rial (the admin form converts from toman). Rules that need
 * the SKU's price or other deals (deal price below the regular price, no overlapping deal) are in SaveFlashDeal.
 */
final class StoreFlashDealRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Exists>>
     */
    public static function baseRules(): array
    {
        return [
            FlashDeal::COLUMN_VARIANT_ID => ['required', 'integer', Rule::exists(ProductVariant::class, 'id')],
            FlashDeal::COLUMN_PROVIDER_ID => ['required', 'integer', Rule::exists('providers', 'id')],
            FlashDeal::COLUMN_DEAL_PRICE => ['required', 'integer', 'min:1'],
            FlashDeal::COLUMN_STARTS_AT => ['required', 'date'],
            FlashDeal::COLUMN_ENDS_AT => ['required', 'date', 'after:'.FlashDeal::COLUMN_STARTS_AT],
            FlashDeal::COLUMN_QUANTITY_LIMIT => ['nullable', 'integer', 'min:1', 'max:100000'],
            FlashDeal::COLUMN_IS_ACTIVE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        $deal = $this->route('flashDeal');

        return $deal instanceof FlashDeal
            ? ($this->user('admin')?->can('update', $deal) ?? false)
            : ($this->user('admin')?->can('create', FlashDeal::class) ?? false);
    }

    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): FlashDealData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return FlashDealData::fromArray($validated);
    }
}
