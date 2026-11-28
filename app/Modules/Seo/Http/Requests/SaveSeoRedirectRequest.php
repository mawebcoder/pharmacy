<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Requests;

use App\Modules\Seo\Data\SeoRedirectData;
use App\Modules\Seo\Models\SeoRedirect;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a redirect (roadmap C5, S38); the Filament form applies the same baseRules(). Whether the addresses
 * make a usable redirect is SaveRedirect's decision (RedirectRefused).
 */
final class SaveSeoRedirectRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        return [
            SeoRedirect::COLUMN_FROM_PATH => ['required', 'string', 'max:500'],
            SeoRedirect::COLUMN_TO_PATH => ['required', 'string', 'max:500'],
            SeoRedirect::COLUMN_STATUS_CODE => ['required', 'integer', 'in:301,302'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Create:SeoRedirect') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): SeoRedirectData
    {
        return SeoRedirectData::fromArray($this->validated());
    }
}
