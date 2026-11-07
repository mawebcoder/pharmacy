<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for creating or updating a category (roadmap C5). The Filament form applies the same baseRules().
 * Tree rules (depth, not under itself) are checked by CreateCategory and UpdateCategory.
 */
final class StoreCategoryRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique|Exists>>
     */
    public static function baseRules(?int $categoryId = null): array
    {
        return [
            Category::COLUMN_PARENT_ID => [
                'nullable', 'integer',
                Rule::exists(Category::class, Category::COLUMN_ID)->withoutTrashed(),
                ...($categoryId === null ? [] : ['not_in:'.$categoryId]),
            ],
            Category::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            Category::COLUMN_NAME_EN => ['nullable', 'string', 'max:255'],
            Category::COLUMN_SLUG => ['nullable', 'string', 'max:255', 'regex:'.StoreBrandRequest::SLUG_PATTERN, Rule::unique(Category::class, Category::COLUMN_SLUG)->ignore($categoryId)],
            Category::COLUMN_DESCRIPTION => ['nullable', 'string', 'max:5000'],
            Category::COLUMN_IS_ACTIVE => ['boolean'],
            Category::COLUMN_SEO_TITLE => ['nullable', 'string', 'max:255'],
            Category::COLUMN_SEO_DESCRIPTION => ['nullable', 'string', 'max:500'],
        ];
    }

    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? ($this->user('admin')?->can('update', $category) ?? false)
            : ($this->user('admin')?->can('create', Category::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique|Exists>>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return self::baseRules($category instanceof Category ? $category->getId() : null);
    }

    public function toDto(): CategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CategoryData::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            Category::COLUMN_NAME_FA => PersianNormalizer::letters(trim((string) $this->input(Category::COLUMN_NAME_FA))),
        ]);
    }
}
