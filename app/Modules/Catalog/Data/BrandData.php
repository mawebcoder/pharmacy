<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a brand. A null slug is generated from the names.
 */
final readonly class BrandData
{
    public function __construct(
        public string $nameFa,
        public ?string $nameEn,
        public ?string $slug,
        public ?string $country,
        public ?string $description,
        public bool $isActive,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}

    /**
     * Keys are the brands columns (Brand::COLUMN_*).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nameFa: PersianNormalizer::letters(trim((string) ($data[Brand::COLUMN_NAME_FA] ?? ''))),
            nameEn: self::text($data[Brand::COLUMN_NAME_EN] ?? null),
            slug: self::text($data[Brand::COLUMN_SLUG] ?? null),
            country: ($country = self::text($data[Brand::COLUMN_COUNTRY] ?? null)) === null ? null : strtoupper($country),
            description: self::text($data[Brand::COLUMN_DESCRIPTION] ?? null),
            isActive: (bool) ($data[Brand::COLUMN_IS_ACTIVE] ?? true),
            seoTitle: self::text($data[Brand::COLUMN_SEO_TITLE] ?? null),
            seoDescription: self::text($data[Brand::COLUMN_SEO_DESCRIPTION] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
