<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use App\Modules\Pricing\Models\Provider;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a supplier.
 */
final readonly class ProviderData
{
    public function __construct(
        public string $name,
        public ?string $contactName,
        public ?string $phone,
        public ?string $address,
        public ?int $cityId,
        public ?string $paymentTerms,
        public bool $isActive,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the providers columns (Provider::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $text = function (string $key) use ($data): ?string {
            $value = trim((string) ($data[$key] ?? ''));

            return $value === '' ? null : $value;
        };

        return new self(
            name: PersianNormalizer::letters((string) $text(Provider::COLUMN_NAME)),
            contactName: $text(Provider::COLUMN_CONTACT_NAME),
            phone: ($phone = $text(Provider::COLUMN_PHONE)) === null ? null : PersianNormalizer::digits($phone),
            address: $text(Provider::COLUMN_ADDRESS),
            cityId: blank($data[Provider::COLUMN_CITY_ID] ?? null) ? null : (int) $data[Provider::COLUMN_CITY_ID],
            paymentTerms: $text(Provider::COLUMN_PAYMENT_TERMS),
            isActive: (bool) ($data[Provider::COLUMN_IS_ACTIVE] ?? true),
            notes: $text(Provider::COLUMN_NOTES),
        );
    }
}
