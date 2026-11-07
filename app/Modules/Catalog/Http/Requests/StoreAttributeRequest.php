<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for an attribute (roadmap C5). The code is a stable machine name used in filter URLs (?form[]=…).
 */
final class StoreAttributeRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public static function baseRules(?int $attributeId = null): array
    {
        return [
            Attribute::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            Attribute::COLUMN_CODE => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(Attribute::class, Attribute::COLUMN_CODE)->ignore($attributeId)],
            Attribute::COLUMN_TYPE => ['required', Rule::enum(AttributeType::class)],
            Attribute::COLUMN_IS_FILTERABLE => ['boolean'],
            Attribute::COLUMN_SORT => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        $attribute = $this->route('attribute');

        return $attribute instanceof Attribute
            ? ($this->user('admin')?->can('update', $attribute) ?? false)
            : ($this->user('admin')?->can('create', Attribute::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public function rules(): array
    {
        $attribute = $this->route('attribute');

        return self::baseRules($attribute instanceof Attribute ? $attribute->getId() : null);
    }
}
