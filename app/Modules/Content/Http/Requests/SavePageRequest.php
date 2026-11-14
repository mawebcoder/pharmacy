<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Data\PageData;
use App\Modules\Content\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for a static page (roadmap C5). The Filament form applies the same baseRules(). Slugs are words of
 * letters or digits joined by «-» (Persian letters allowed), unique among all pages, deleted ones included.
 */
final class SavePageRequest extends FormRequest
{
    public const string SLUG_PATTERN = '/^[\p{Ll}\p{Lo}\p{N}]+(?:-[\p{Ll}\p{Lo}\p{N}]+)*$/u';

    /**
     * @return array<string, array<int, string|Unique>>
     */
    public static function baseRules(?int $pageId = null): array
    {
        return [
            Page::COLUMN_TITLE => ['required', 'string', 'max:120'],
            Page::COLUMN_SLUG => ['required', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, Rule::unique(Page::class, Page::COLUMN_SLUG)->ignore($pageId)],
            Page::COLUMN_BODY => ['nullable', 'string'],
            Page::COLUMN_IS_ACTIVE => ['boolean'],
            Page::COLUMN_SEO_TITLE => ['nullable', 'string', 'max:120'],
            Page::COLUMN_SEO_DESCRIPTION => ['nullable', 'string', 'max:300'],
        ];
    }

    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? ($this->user('admin')?->can('update', $page) ?? false)
            : ($this->user('admin')?->can('create', Page::class) ?? false);
    }

    /**
     * @return array<string, array<int, string|Unique>>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        return self::baseRules($page instanceof Page ? $page->getId() : null);
    }

    public function toDto(): PageData
    {
        return PageData::fromArray($this->validated());
    }
}
