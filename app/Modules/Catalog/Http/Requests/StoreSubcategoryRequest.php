<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for creating or updating a subcategory (roadmap C5; owner, 2026-11-15). The admin «زیردسته‌ها» tab
 * applies the same baseRules().
 */
final class StoreSubcategoryRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique>>
     */
    public static function baseRules(?int $subcategoryId = null): array
    {
        return [
            Subcategory::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            Subcategory::COLUMN_NAME_EN => ['nullable', 'string', 'max:255'],
            Subcategory::COLUMN_SLUG => ['nullable', 'string', 'max:255', 'regex:'.StoreBrandRequest::SLUG_PATTERN, Rule::unique(Subcategory::class, Subcategory::COLUMN_SLUG)->ignore($subcategoryId)],
            Subcategory::COLUMN_DESCRIPTION => ['nullable', 'string', 'max:5000'],
            Subcategory::COLUMN_IS_ACTIVE => ['boolean'],
            Subcategory::COLUMN_SEO_TITLE => ['nullable', 'string', 'max:255'],
            Subcategory::COLUMN_SEO_DESCRIPTION => ['nullable', 'string', 'max:500'],
        ];
    }

    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category && ($this->user('admin')?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, list<string|Unique>>
     */
    public function rules(): array
    {
        $subcategory = $this->route('subcategory');

        return self::baseRules($subcategory instanceof Subcategory ? $subcategory->getId() : null);
    }

    public function toDto(): SubcategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SubcategoryData::fromArray($validated);
    }
}
