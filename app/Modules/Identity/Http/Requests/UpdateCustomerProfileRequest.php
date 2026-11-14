<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\CustomerProfileData;
use App\Modules\Identity\Enums\Gender;
use App\Modules\Identity\Models\Customer;
use App\Modules\Shared\Rules\IranianNationalId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validation for «اطلاعات حساب» (roadmap C5, D3.3). The account Livewire page applies the same baseRules(). The
 * birth date is three Jalali parts that are all given or all empty.
 */
final class UpdateCustomerProfileRequest extends FormRequest
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, string|ValidationRule|Closure|Enum>>
     */
    public static function baseRules(array $input): array
    {
        return [
            Customer::COLUMN_FIRST_NAME => ['required', 'string', 'max:60'],
            Customer::COLUMN_LAST_NAME => ['required', 'string', 'max:60'],
            Customer::COLUMN_NATIONAL_ID => ['nullable', new IranianNationalId],
            Customer::COLUMN_EMAIL => ['nullable', 'email', 'max:120'],
            Customer::COLUMN_GENDER => ['nullable', Rule::enum(Gender::class)],
            // The Jalali picker sends a Gregorian Y-m-d (decided 2026-11-14, S18).
            Customer::COLUMN_BIRTH_DATE => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:-110 years'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            Customer::COLUMN_FIRST_NAME => 'نام',
            Customer::COLUMN_LAST_NAME => 'نام خانوادگی',
            Customer::COLUMN_NATIONAL_ID => 'کد ملی',
            Customer::COLUMN_EMAIL => 'ایمیل',
            Customer::COLUMN_GENDER => 'جنسیت',
            Customer::COLUMN_BIRTH_DATE => 'تاریخ تولد',
        ];
    }

    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Closure|Enum>>
     */
    public function rules(): array
    {
        return self::baseRules($this->all());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }

    public function toDto(): CustomerProfileData
    {
        return CustomerProfileData::fromArray($this->validated());
    }
}
