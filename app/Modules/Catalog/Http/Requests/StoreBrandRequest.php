<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Shared\Support\Countries;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for creating or updating a brand (roadmap C5). The Filament form applies the same baseRules().
 */
final class StoreBrandRequest extends FormRequest
{
    /**
     * A slug is lowercase letters (Persian or Latin), digits and single hyphens.
     */
    public const string SLUG_PATTERN = '/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u';

    /**
     * @return array<string, list<string|Unique|In>>
     */
    public static function baseRules(?int $brandId = null): array
    {
        return [
            Brand::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            Brand::COLUMN_NAME_EN => ['nullable', 'string', 'max:255'],
            Brand::COLUMN_SLUG => ['nullable', 'string', 'max:255', 'regex:'.self::SLUG_PATTERN, Rule::unique(Brand::class, Brand::COLUMN_SLUG)->ignore($brandId)],
            Brand::COLUMN_COUNTRY => ['nullable', 'string', Rule::in(Countries::codes())],
            Brand::COLUMN_DESCRIPTION => ['nullable', 'string', 'max:5000'],
            Brand::COLUMN_COMMISSION_PERCENT => ['nullable', 'numeric', 'min:0', 'max:100'],
            Brand::COLUMN_IS_ACTIVE => ['boolean'],
            Brand::COLUMN_SEO_TITLE => ['nullable', 'string', 'max:255'],
            Brand::COLUMN_SEO_DESCRIPTION => ['nullable', 'string', 'max:500'],
        ];
    }

    public function authorize(): bool
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand
            ? ($this->user('admin')?->can('update', $brand) ?? false)
            : ($this->user('admin')?->can('create', Brand::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique|In>>
     */
    public function rules(): array
    {
        $brand = $this->route('brand');

        return self::baseRules($brand instanceof Brand ? $brand->getId() : null);
    }

    public function toDto(): BrandData
    {
        /** @var array{name_fa: string, name_en?: string|null, slug?: string|null, country?: string|null, description?: string|null, is_active?: bool, seo_title?: string|null, seo_description?: string|null} $validated */
        $validated = $this->validated();

        return BrandData::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            Brand::COLUMN_NAME_FA => PersianNormalizer::letters(trim((string) $this->input(Brand::COLUMN_NAME_FA))),
            Brand::COLUMN_COUNTRY => $this->filled(Brand::COLUMN_COUNTRY) ? strtoupper((string) $this->input(Brand::COLUMN_COUNTRY)) : null,
        ]);
    }
}
