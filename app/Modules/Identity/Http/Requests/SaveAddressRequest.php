<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\AddressData;
use App\Modules\Identity\Models\Address;
use App\Modules\Locations\Queries\DeliveryLocations;
use App\Modules\Shared\Rules\IranianMobile;
use App\Modules\Shared\Rules\IranianPostalCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a customer's delivery address (roadmap C5, D3.3). The account Livewire page applies the same
 * baseRules(); the city must be a delivery city (a Tipax city with delivery on) of the chosen province.
 */
final class SaveAddressRequest extends FormRequest
{
    /**
     * @param  array<string, mixed>  $input  The submitted values, for the rules that compare fields.
     * @return array<string, array<int, string|ValidationRule|Closure>>
     */
    public static function baseRules(array $input): array
    {
        $locations = app(DeliveryLocations::class);

        return [
            Address::COLUMN_TITLE => ['required', 'string', 'max:40'],
            Address::COLUMN_RECEIVER_NAME => ['required', 'string', 'max:80'],
            Address::COLUMN_RECEIVER_MOBILE => ['required', new IranianMobile],
            Address::COLUMN_PROVINCE_ID => ['required', 'integer'],
            Address::COLUMN_CITY_ID => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($locations, $input): void {
                if (! $locations->isDeliveryCity((int) ($input[Address::COLUMN_PROVINCE_ID] ?? 0), (int) $value)) {
                    $fail('ارسال به این شهر فعال نیست یا شهر در استان انتخاب‌شده نیست.');
                }
            }],
            Address::COLUMN_FULL_ADDRESS => ['required', 'string', 'min:10', 'max:500'],
            Address::COLUMN_PLAQUE => ['required', 'string', 'max:10'],
            Address::COLUMN_UNIT => ['nullable', 'string', 'max:10'],
            Address::COLUMN_FLOOR => ['nullable', 'string', 'max:10'],
            Address::COLUMN_POSTAL_CODE => ['required', new IranianPostalCode],
            Address::COLUMN_IS_DEFAULT => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            Address::COLUMN_TITLE => 'عنوان',
            Address::COLUMN_RECEIVER_NAME => 'نام گیرنده',
            Address::COLUMN_RECEIVER_MOBILE => 'موبایل گیرنده',
            Address::COLUMN_PROVINCE_ID => 'استان',
            Address::COLUMN_CITY_ID => 'شهر',
            Address::COLUMN_FULL_ADDRESS => 'نشانی',
            Address::COLUMN_PLAQUE => 'پلاک',
            Address::COLUMN_UNIT => 'واحد',
            Address::COLUMN_FLOOR => 'طبقه',
            Address::COLUMN_POSTAL_CODE => 'کد پستی',
        ];
    }

    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Closure>>
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

    public function toDto(): AddressData
    {
        return AddressData::fromArray($this->validated());
    }
}
