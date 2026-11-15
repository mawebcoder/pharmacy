<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Category;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a category. A null slug is generated from the names.
 */
final readonly class CategoryData
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
     * Keys are the categories columns (Category::COLUMN_*).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nameFa: PersianNormalizer::letters(trim((string) ($data[Category::COLUMN_NAME_FA] ?? ''))),
            nameEn: self::text($data[Category::COLUMN_NAME_EN] ?? null),
            slug: self::text($data[Category::COLUMN_SLUG] ?? null),
            description: self::text($data[Category::COLUMN_DESCRIPTION] ?? null),
            isActive: (bool) ($data[Category::COLUMN_IS_ACTIVE] ?? true),
            seoTitle: self::text($data[Category::COLUMN_SEO_TITLE] ?? null),
            seoDescription: self::text($data[Category::COLUMN_SEO_DESCRIPTION] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
