<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\PharmacistData;
use App\Modules\Settings\Models\Pharmacist;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a pharmacist (roadmap C5, S35); the Filament form applies the same baseRules(). The council number
 * is digits, Persian or Latin.
 */
final class SavePharmacistRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        return [
            Pharmacist::COLUMN_FULL_NAME => ['required', 'string', 'max:80'],
            Pharmacist::COLUMN_LICENSE_NUMBER => ['required', 'string', 'max:20', 'regex:/^[0-9۰-۹\-]+$/u'],
            Pharmacist::COLUMN_BIO => ['nullable', 'string', 'max:1000'],
            Pharmacist::COLUMN_IS_RESPONSIBLE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Create:Pharmacist') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): PharmacistData
    {
        return PharmacistData::fromArray($this->validated());
    }
}
