<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a subcategory (owner, 2026-11-15). A null slug is generated from the names.
 */
final readonly class SubcategoryData
{
    public function __construct(
        public string $nameFa,
        public ?string $nameEn,
        public ?string $slug,
        public ?string $description,
        public bool $isActive,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}

    /**
     * Keys are the subcategories columns (Subcategory::COLUMN_*).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nameFa: PersianNormalizer::letters(trim((string) ($data[Subcategory::COLUMN_NAME_FA] ?? ''))),
            nameEn: self::text($data[Subcategory::COLUMN_NAME_EN] ?? null),
            slug: self::text($data[Subcategory::COLUMN_SLUG] ?? null),
            description: self::text($data[Subcategory::COLUMN_DESCRIPTION] ?? null),
            isActive: (bool) ($data[Subcategory::COLUMN_IS_ACTIVE] ?? true),
            seoTitle: self::text($data[Subcategory::COLUMN_SEO_TITLE] ?? null),
            seoDescription: self::text($data[Subcategory::COLUMN_SEO_DESCRIPTION] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
