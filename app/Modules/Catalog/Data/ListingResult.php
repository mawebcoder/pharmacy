<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A page of a product list with its filter groups and counts. Degraded when search was unavailable and the
 * list came from the database without text search or counts.
 */
final readonly class ListingResult
{
    /**
     * @param  LengthAwarePaginator<int, Product>  $products
     * @param  list<FacetGroup>  $facets  brands first, then attributes in their order
     * @param  array{in_stock: int, near_expiry: int, damaged_box: int}  $flagCounts
     */
    public function __construct(
        public LengthAwarePaginator $products,
        public array $facets,
        public ?int $priceFloor,
        public ?int $priceCeiling,
        public array $flagCounts,
        public bool $degraded,
    ) {}
}
