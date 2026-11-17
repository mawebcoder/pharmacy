<?php

declare(strict_types=1);

namespace App\Modules\Settings\Data;

use App\Modules\Settings\Models\Pharmacist;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for a pharmacist: plain-text name and bio, the council number in Latin digits.
 */
final readonly class PharmacistData
{
    public function __construct(
        public string $fullName,
        public string $licenseNumber,
        public ?string $bio,
        public bool $isResponsible,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the pharmacists columns (Pharmacist::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $bio = PersianNormalizer::normalize(trim(strip_tags((string) ($data[Pharmacist::COLUMN_BIO] ?? ''))));

        return new self(
            fullName: PersianNormalizer::normalize(trim(strip_tags((string) ($data[Pharmacist::COLUMN_FULL_NAME] ?? '')))),
            licenseNumber: PersianDigits::toLatin(trim((string) ($data[Pharmacist::COLUMN_LICENSE_NUMBER] ?? ''))),
            bio: $bio === '' ? null : $bio,
            isResponsible: (bool) ($data[Pharmacist::COLUMN_IS_RESPONSIBLE] ?? false),
        );
    }
}
