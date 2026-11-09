<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\ListingSort;

/**
 * What a product list shows (roadmap B9.3, D21.2): the page's own scope (category, brand, tag or search text) and
 * the shopper's filters, sort and page. Prices in rial.
 */
final readonly class ListingCriteria
{
    /**
     * @param  list<int>  $brandIds
     * @param  array<string, list<int>>  $attributeValueIds  attribute code => chosen value ids
     */
    public function __construct(
        public ?string $query = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public ?int $tagId = null,
        public ?int $collectionId = null,
        public array $brandIds = [],
        public array $attributeValueIds = [],
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public bool $inStock = false,
        public ?int $minDiscount = null,
        public bool $nearExpiry = false,
        public bool $damagedBox = false,
        public ListingSort $sort = ListingSort::Newest,
        public int $page = 1,
        public int $perPage = 24,
    ) {}

    public function isSearch(): bool
    {
        return $this->query !== null && trim($this->query) !== '';
    }
}
