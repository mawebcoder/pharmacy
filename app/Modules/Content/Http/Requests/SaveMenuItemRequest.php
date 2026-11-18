<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Data\MenuItemData;
use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Enums\MenuIcon;
use App\Modules\Content\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validation for a menu item (roadmap C5, S29). The Filament form applies the same baseRules(); the picked item and
 * the https address are required by the form's fields for their own target types.
 */
final class SaveMenuItemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum>>
     */
    public static function baseRules(): array
    {
        return [
            MenuItem::COLUMN_TITLE => ['required', 'string', 'max:60'],
            MenuItem::COLUMN_ICON => ['nullable', Rule::enum(MenuIcon::class)],
            MenuItem::COLUMN_LINK_TYPE => ['required', Rule::enum(LinkType::class)],
            MenuItem::COLUMN_EXTERNAL_URL => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            MenuItem::COLUMN_IS_ACTIVE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Update:Menu') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): MenuItemData
    {
        return MenuItemData::fromArray($this->validated());
    }
}
