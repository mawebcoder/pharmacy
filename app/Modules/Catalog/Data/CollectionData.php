<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Support\Carbon;

/**
 * Input for creating or updating a collection. The schedule times are UTC; blank means no limit.
 */
final readonly class CollectionData
{
    public function __construct(
        public string $nameFa,
        public ?string $nameEn,
        public ?string $slug,
        public ?string $description,
        public bool $isActive,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
        public int $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the collections columns (ProductCollection::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $text = function (string $key) use ($data): ?string {
            $value = trim((string) ($data[$key] ?? ''));

            return $value === '' ? null : $value;
        };
        $time = fn (string $key): ?Carbon => blank($data[$key] ?? null) ? null : Carbon::parse($data[$key])->utc();

        return new self(
            nameFa: PersianNormalizer::letters((string) $text(ProductCollection::COLUMN_NAME_FA)),
            nameEn: $text(ProductCollection::COLUMN_NAME_EN),
            slug: $text(ProductCollection::COLUMN_SLUG),
            description: $text(ProductCollection::COLUMN_DESCRIPTION),
            isActive: (bool) ($data[ProductCollection::COLUMN_IS_ACTIVE] ?? true),
            startsAt: $time(ProductCollection::COLUMN_STARTS_AT),
            endsAt: $time(ProductCollection::COLUMN_ENDS_AT),
            sort: (int) ($data[ProductCollection::COLUMN_SORT] ?? 0),
        );
    }
}
