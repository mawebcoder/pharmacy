<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * A filter group of a product list: the brands, or one filterable attribute (key = its code).
 */
final readonly class FacetGroup
{
    /**
     * @param  list<FacetOption>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $options,
    ) {}
}
