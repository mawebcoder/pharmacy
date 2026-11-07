<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for creating or updating a product (roadmap C5). The Filament form applies the same baseRules().
 * Sales rules (activation, prescription guard) are checked by the actions.
 */
final class StoreProductRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique|Exists|Enum>>
     */
    public static function baseRules(?int $productId = null): array
    {
        $text = ['nullable', 'string', 'max:20000'];

        return [
            Product::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            Product::COLUMN_NAME_EN => ['nullable', 'string', 'max:255'],
            Product::COLUMN_SLUG => ['nullable', 'string', 'max:255', 'regex:'.StoreBrandRequest::SLUG_PATTERN, Rule::unique(Product::class, Product::COLUMN_SLUG)->ignore($productId)],
            Product::COLUMN_BRAND_ID => ['required', 'integer', Rule::exists(Brand::class, Brand::COLUMN_ID)->withoutTrashed()],
            Product::COLUMN_PRIMARY_CATEGORY_ID => ['required', 'integer', Rule::exists(Category::class, Category::COLUMN_ID)->withoutTrashed()],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', Rule::exists(Category::class, Category::COLUMN_ID)->withoutTrashed()],
            'attribute_value_ids' => ['array'],
            'attribute_value_ids.*' => ['integer', Rule::exists(AttributeValue::class, AttributeValue::COLUMN_ID)],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', Rule::exists(Tag::class, Tag::COLUMN_ID)->withoutTrashed()],
            Product::COLUMN_PRODUCT_TYPE => ['required', Rule::enum(ProductType::class)],
            Product::COLUMN_REQUIRES_PRESCRIPTION => ['boolean'],
            Product::COLUMN_IRC_CODE => ['nullable', 'string', 'max:32'],
            Product::COLUMN_SHORT_DESCRIPTION => ['nullable', 'string', 'max:1000'],
            Product::COLUMN_DESCRIPTION => $text,
            Product::COLUMN_INGREDIENTS => $text,
            Product::COLUMN_USAGE => $text,
            Product::COLUMN_WARNINGS => $text,
            Product::COLUMN_SIDE_EFFECTS => $text,
            Product::COLUMN_INTERACTIONS => $text,
            Product::COLUMN_STATUS => ['required', Rule::enum(ProductStatus::class)],
            Product::COLUMN_SEO_TITLE => ['nullable', 'string', 'max:255'],
            Product::COLUMN_SEO_DESCRIPTION => ['nullable', 'string', 'max:500'],
        ];
    }

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? ($this->user('admin')?->can('update', $product) ?? false)
            : ($this->user('admin')?->can('create', Product::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique|Exists|Enum>>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return self::baseRules($product instanceof Product ? $product->getId() : null);
    }

    public function toDto(): ProductData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProductData::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            Product::COLUMN_NAME_FA => PersianNormalizer::letters(trim((string) $this->input(Product::COLUMN_NAME_FA))),
        ]);
    }
}
