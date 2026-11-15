<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * Where one category sits after a drag and drop in the admin categories table (one level; owner, 2026-11-15).
 */
final readonly class CategoryPositionData
{
    public function __construct(
        public int $categoryId,
        public int $sort,
    ) {}

    /**
     * The ids in their new order, numbered from 1.
     *
     * @param  array<int|string>  $orderedIds
     * @return list<self>
     */
    public static function fromOrderedIds(array $orderedIds): array
    {
        return array_map(fn (int $index, int|string $id): self => new self((int) $id, $index + 1), array_keys(array_values($orderedIds)), array_values($orderedIds));
    }
}
