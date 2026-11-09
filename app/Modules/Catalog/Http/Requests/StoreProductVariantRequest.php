<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for a product variant (roadmap C5). Prices are rial here (the admin form converts from toman).
 * There is no selling price (Pricing calculates it, roadmap D3.2); rules that depend on the product (commission,
 * haraj below the current price, medicines need a printed price) are checked by the actions.
 */
final class StoreProductVariantRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public static function baseRules(?int $variantId = null): array
    {
        $size = ['nullable', 'integer', 'min:1', 'max:5000'];

        return [
            ProductVariant::COLUMN_TITLE_FA => ['required', 'string', 'max:255'],
            ProductVariant::COLUMN_SKU => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(ProductVariant::class, ProductVariant::COLUMN_SKU)->ignore($variantId)],
            ProductVariant::COLUMN_BARCODE => ['nullable', 'string', 'max:64', 'regex:/^[0-9A-Za-z-]+$/'],
            ProductVariant::COLUMN_OFFICIAL_PRICE => ['nullable', 'integer', 'min:0'],
            ProductVariant::COLUMN_SALE_PRICE => ['nullable', 'integer', 'min:1'],
            ProductVariant::COLUMN_STOCK_STATUS => ['required', Rule::enum(StockStatus::class)],
            ProductVariant::COLUMN_EXPIRY_DATE => ['nullable', 'date'],
            ProductVariant::COLUMN_IS_DAMAGED_BOX => ['boolean'],
            ProductVariant::COLUMN_WEIGHT_G => ['nullable', 'integer', 'min:1', 'max:100000'],
            ProductVariant::COLUMN_LENGTH_MM => $size,
            ProductVariant::COLUMN_WIDTH_MM => $size,
            ProductVariant::COLUMN_HEIGHT_MM => $size,
            ProductVariant::COLUMN_DIMENSIONS_VERIFIED => ['boolean'],
            ProductVariant::COLUMN_SHIP_SEPARATELY => ['boolean'],
            ProductVariant::COLUMN_IS_FRAGILE => ['boolean'],
            ProductVariant::COLUMN_IS_LIQUID => ['boolean'],
            ProductVariant::COLUMN_REQUIRES_COLD_CHAIN => ['boolean'],
            ProductVariant::COLUMN_MAX_PER_ORDER => ['nullable', 'integer', 'min:1', 'max:999'],
            ProductVariant::COLUMN_IS_ACTIVE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('ViewAny:Product') ?? false;
    }

    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public function rules(): array
    {
        $variant = $this->route('variant');

        return self::baseRules($variant instanceof ProductVariant ? $variant->getId() : null);
    }

    public function toDto(): ProductVariantData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProductVariantData::fromArray($validated);
    }
}
