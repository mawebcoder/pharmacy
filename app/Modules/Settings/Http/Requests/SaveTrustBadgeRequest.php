<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\TrustBadgeData;
use App\Modules\Settings\Models\TrustBadge;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a trust badge (roadmap C5, S35); the Filament form applies the same baseRules(). Whether the embed
 * code is one we accept is SaveTrustBadge's decision (TrustBadgeRefused).
 */
final class SaveTrustBadgeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        return [
            TrustBadge::COLUMN_TITLE => ['required', 'string', 'max:80'],
            TrustBadge::COLUMN_EMBED_CODE => ['nullable', 'string', 'max:5000'],
            TrustBadge::COLUMN_LINK_URL => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            TrustBadge::COLUMN_IS_ACTIVE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Create:TrustBadge') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): TrustBadgeData
    {
        return TrustBadgeData::fromArray($this->validated());
    }
}
