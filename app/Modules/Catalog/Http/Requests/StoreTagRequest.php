<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\TagData;
use App\Modules\Catalog\Enums\TagType;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for a tag (roadmap C5). The Filament form applies the same baseRules().
 */
final class StoreTagRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public static function baseRules(?int $tagId = null): array
    {
        return [
            Tag::COLUMN_NAME_FA => ['required', 'string', 'max:255'],
            Tag::COLUMN_NAME_EN => ['nullable', 'string', 'max:255'],
            Tag::COLUMN_SLUG => ['nullable', 'string', 'max:255', 'regex:'.StoreBrandRequest::SLUG_PATTERN, Rule::unique(Tag::class, Tag::COLUMN_SLUG)->ignore($tagId)],
            Tag::COLUMN_TYPE => ['required', Rule::enum(TagType::class)],
            Tag::COLUMN_IS_FEATURED => ['boolean'],
            Tag::COLUMN_SORT => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? ($this->user('admin')?->can('update', $tag) ?? false)
            : ($this->user('admin')?->can('create', Tag::class) ?? false);
    }

    /**
     * @return array<string, list<string|Unique|Enum>>
     */
    public function rules(): array
    {
        $tag = $this->route('tag');

        return self::baseRules($tag instanceof Tag ? $tag->getId() : null);
    }

    public function toDto(): TagData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TagData::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([Tag::COLUMN_NAME_FA => PersianNormalizer::letters(trim((string) $this->input(Tag::COLUMN_NAME_FA)))]);
    }
}
