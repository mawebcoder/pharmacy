<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\TagType;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a tag. A null slug is generated from the names.
 */
final readonly class TagData
{
    public function __construct(
        public string $nameFa,
        public ?string $nameEn,
        public ?string $slug,
        public TagType $type,
        public bool $isFeatured,
        public int $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the tags columns (Tag::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $type = $data[Tag::COLUMN_TYPE] ?? TagType::General;
        $nameEn = trim((string) ($data[Tag::COLUMN_NAME_EN] ?? ''));
        $slug = trim((string) ($data[Tag::COLUMN_SLUG] ?? ''));

        return new self(
            nameFa: PersianNormalizer::letters(trim((string) ($data[Tag::COLUMN_NAME_FA] ?? ''))),
            nameEn: $nameEn === '' ? null : $nameEn,
            slug: $slug === '' ? null : $slug,
            type: $type instanceof TagType ? $type : TagType::from((string) $type),
            isFeatured: (bool) ($data[Tag::COLUMN_IS_FEATURED] ?? false),
            sort: (int) ($data[Tag::COLUMN_SORT] ?? 0),
        );
    }
}
