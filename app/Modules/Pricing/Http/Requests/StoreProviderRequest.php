<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Requests;

use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Models\Provider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation for a supplier (roadmap C5); the admin form reuses baseRules().
 */
final class StoreProviderRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Exists>>
     */
    public static function baseRules(): array
    {
        return [
            Provider::COLUMN_NAME => ['required', 'string', 'max:255'],
            Provider::COLUMN_CONTACT_NAME => ['nullable', 'string', 'max:255'],
            // Persian and Arabic digits are accepted; ProviderData stores Latin digits.
            Provider::COLUMN_PHONE => ['nullable', 'string', 'max:32', 'regex:/^[0-9۰-۹٠-٩+\-\s]+$/u'],
            Provider::COLUMN_ADDRESS => ['nullable', 'string', 'max:500'],
            Provider::COLUMN_CITY_ID => ['nullable', 'integer', Rule::exists('cities', 'id')],
            Provider::COLUMN_PAYMENT_TERMS => ['nullable', 'string', 'max:255'],
            Provider::COLUMN_IS_ACTIVE => ['boolean'],
            Provider::COLUMN_NOTES => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function authorize(): bool
    {
        $provider = $this->route('provider');

        return $provider instanceof Provider
            ? ($this->user('admin')?->can('update', $provider) ?? false)
            : ($this->user('admin')?->can('create', Provider::class) ?? false);
    }

    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): ProviderData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProviderData::fromArray($validated);
    }
}
