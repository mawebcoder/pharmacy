<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Requests;

use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for a supplier's price of a SKU (roadmap C5). Cost in Rial here (the admin form converts from toman).
 * A supplier appears once per SKU.
 */
final class StoreProviderPriceRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Exists|Unique>>
     */
    public static function baseRules(int $variantId, ?int $providerPriceId = null): array
    {
        return [
            ProviderPrice::COLUMN_PROVIDER_ID => [
                'required', 'integer', Rule::exists(Provider::class, Provider::COLUMN_ID),
                Rule::unique(ProviderPrice::class, ProviderPrice::COLUMN_PROVIDER_ID)
                    ->where(ProviderPrice::COLUMN_VARIANT_ID, $variantId)
                    ->ignore($providerPriceId),
            ],
            ProviderPrice::COLUMN_VARIANT_ID => ['required', 'integer', Rule::exists('product_variants', 'id')],
            ProviderPrice::COLUMN_COST => ['required', 'integer', 'min:1'],
            ProviderPrice::COLUMN_IS_AVAILABLE => ['boolean'],
            ProviderPrice::COLUMN_IS_ENABLED => ['boolean'],
            ProviderPrice::COLUMN_LEAD_TIME_HOURS => ['nullable', 'integer', 'min:0', 'max:2000'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Update:Provider') ?? false;
    }

    /**
     * @return array<string, list<string|Exists|Unique>>
     */
    public function rules(): array
    {
        $price = $this->route('providerPrice');

        return self::baseRules((int) $this->input(ProviderPrice::COLUMN_VARIANT_ID), $price instanceof ProviderPrice ? $price->getId() : null);
    }

    public function toDto(): ProviderPriceData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProviderPriceData::fromArray($validated);
    }
}
