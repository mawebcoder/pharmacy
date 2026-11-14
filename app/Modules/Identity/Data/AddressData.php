<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Models\Address;
use App\Modules\Shared\Rules\IranianPostalCode;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * A delivery address as the customer typed it, normalised (roadmap D3.3): Latin digits, «09…» mobile, 10-digit
 * postal code, empty optional parts as null.
 */
final readonly class AddressData
{
    public function __construct(
        public string $title,
        public string $receiverName,
        public string $receiverMobile,
        public int $provinceId,
        public int $cityId,
        public string $fullAddress,
        public string $plaque,
        public ?string $unit,
        public ?string $floor,
        public string $postalCode,
        public bool $isDefault,
    ) {}

    /**
     * Keys are the addresses columns (Address::COLUMN_*), already validated by SaveAddressRequest::baseRules().
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): string => PersianNormalizer::normalize(trim((string) ($data[$key] ?? '')));
        $optional = static fn (string $key): ?string => ($value = PersianNormalizer::digits(trim((string) ($data[$key] ?? '')))) === '' ? null : $value;

        return new self(
            title: $text(Address::COLUMN_TITLE),
            receiverName: $text(Address::COLUMN_RECEIVER_NAME),
            receiverMobile: (string) IranMobile::normalize((string) ($data[Address::COLUMN_RECEIVER_MOBILE] ?? '')),
            provinceId: (int) ($data[Address::COLUMN_PROVINCE_ID] ?? 0),
            cityId: (int) ($data[Address::COLUMN_CITY_ID] ?? 0),
            fullAddress: $text(Address::COLUMN_FULL_ADDRESS),
            plaque: (string) $optional(Address::COLUMN_PLAQUE),
            unit: $optional(Address::COLUMN_UNIT),
            floor: $optional(Address::COLUMN_FLOOR),
            postalCode: IranianPostalCode::normalize((string) ($data[Address::COLUMN_POSTAL_CODE] ?? '')),
            isDefault: (bool) ($data[Address::COLUMN_IS_DEFAULT] ?? false),
        );
    }
}
