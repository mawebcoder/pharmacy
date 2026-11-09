<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for collections (roadmap S13b); the admin form uses baseRules().
 */
final class StoreCollectionRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique>>
     */
    public static function baseRules(?int $collectionId = null): array
    {
        return [
            ProductCollection::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            ProductCollection::COLUMN_NAME_EN => ['nullable', 'string', 'max:255'],
            ProductCollection::COLUMN_SLUG => ['nullable', 'string', 'max:255', 'regex:'.StoreBrandRequest::SLUG_PATTERN, Rule::unique(ProductCollection::class, ProductCollection::COLUMN_SLUG)->ignore($collectionId)],
            ProductCollection::COLUMN_DESCRIPTION => ['nullable', 'string', 'max:2000'],
            ProductCollection::COLUMN_IS_ACTIVE => ['boolean'],
            ProductCollection::COLUMN_STARTS_AT => ['nullable', 'date'],
            ProductCollection::COLUMN_ENDS_AT => ['nullable', 'date', 'after:'.ProductCollection::COLUMN_STARTS_AT],
            ProductCollection::COLUMN_SORT => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        $collection = $this->route('collection');

        return $collection instanceof ProductCollection
            ? ($this->user('admin')?->can('update', $collection) ?? false)
            : ($this->user('admin')?->can('create', ProductCollection::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique>>
     */
    public function rules(): array
    {
        $collection = $this->route('collection');

        return self::baseRules($collection instanceof ProductCollection ? $collection->getId() : null);
    }

    public function toDto(): CollectionData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CollectionData::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([ProductCollection::COLUMN_NAME_FA => PersianNormalizer::letters(trim((string) $this->input(ProductCollection::COLUMN_NAME_FA)))]);
    }
}
