<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * One active category in the cached storefront tree (menu, category list), with its active subcategories.
 */
final readonly class CategoryNode
{
    /**
     * @param  list<CategoryNode>  $children
     */
    public function __construct(
        public int $id,
        public string $nameFa,
        public string $slug,
        public array $children = [],
    ) {}

    public function url(): string
    {
        return route('category.show', $this->slug);
    }
}
