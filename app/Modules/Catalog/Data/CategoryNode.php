<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * One active category, or one of its subcategories, in the cached storefront tree (menu, category list). A category
 * holds its active subcategories; a subcategory knows its category's slug for its address.
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
        public ?string $categorySlug = null,
    ) {}

    public function isSubcategory(): bool
    {
        return $this->categorySlug !== null;
    }

    public function url(): string
    {
        return $this->categorySlug === null
            ? route('category.show', $this->slug)
            : route('subcategory.show', [$this->categorySlug, $this->slug]);
    }
}
