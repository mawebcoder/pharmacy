<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Enums\Gender;
use App\Modules\Identity\Models\Customer;
use App\Modules\Shared\Support\IranNationalId;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Support\Carbon;

/**
 * The profile a customer edits on «اطلاعات حساب» (roadmap D3.3). The birth date is entered in Jalali (year, month,
 * day) and stored as a Gregorian date. The mobile is the sign-in identity and is not part of it.
 */
final readonly class CustomerProfileData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $nationalId,
        public ?string $email,
        public ?Carbon $birthDate,
        public ?Gender $gender,
    ) {}

    /**
     * Keys are the customers columns (birth_date as a Gregorian Y-m-d from the Jalali picker), validated by
     * UpdateCustomerProfileRequest::baseRules().
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $nationalId = trim((string) ($data[Customer::COLUMN_NATIONAL_ID] ?? ''));
        $email = mb_strtolower(trim((string) ($data[Customer::COLUMN_EMAIL] ?? '')));

        return new self(
            firstName: PersianNormalizer::normalize(trim((string) ($data[Customer::COLUMN_FIRST_NAME] ?? ''))),
            lastName: PersianNormalizer::normalize(trim((string) ($data[Customer::COLUMN_LAST_NAME] ?? ''))),
            nationalId: $nationalId === '' ? null : IranNationalId::normalize($nationalId),
            email: $email === '' ? null : $email,
            birthDate: blank($data[Customer::COLUMN_BIRTH_DATE] ?? null) ? null : Carbon::parse((string) $data[Customer::COLUMN_BIRTH_DATE])->startOfDay(),
            gender: Gender::tryFrom((string) ($data[Customer::COLUMN_GENDER] ?? '')),
        );
    }
}
