<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * The products whose search documents copy a brand, category, subcategory, tag or attribute value (ProductDocument),
 * so only those are rebuilt when it changes. Deleted products are included: their documents are removed.
 */
final readonly class SearchAffectedProducts
{
    /**
     * @return list<int>
     */
    public function ofBrand(int $brandId): array
    {
        return $this->ids(Product::withTrashed()->where(Product::COLUMN_BRAND_ID, $brandId));
    }

    /**
     * Main or extra category, or placed in one of its subcategories.
     *
     * @return list<int>
     */
    public function ofCategory(int $categoryId): array
    {
        return $this->ids(Product::withTrashed()->where(fn (Builder $query) => $query
            ->where(Product::COLUMN_PRIMARY_CATEGORY_ID, $categoryId)
            ->orWhereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId))
            ->orWhereHas('subcategories', fn (Builder $subcategories) => $subcategories->where(Subcategory::COLUMN_CATEGORY_ID, $categoryId))));
    }

    /**
     * Main or extra subcategory.
     *
     * @return list<int>
     */
    public function ofSubcategory(int $subcategoryId): array
    {
        return $this->ids(Product::withTrashed()->where(fn (Builder $query) => $query
            ->where(Product::COLUMN_PRIMARY_SUBCATEGORY_ID, $subcategoryId)
            ->orWhereHas('subcategories', fn (Builder $subcategories) => $subcategories->whereKey($subcategoryId))));
    }

    /**
     * @return list<int>
     */
    public function ofTag(int $tagId): array
    {
        return $this->ids(Product::withTrashed()->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tagId)));
    }

    /**
     * @return list<int>
     */
    public function ofAttributeValue(int $attributeValueId): array
    {
        return $this->ids(Product::withTrashed()->whereHas('attributeValues', fn (Builder $values) => $values->whereKey($attributeValueId)));
    }

    /**
     * @param  Builder<Product>  $products
     * @return list<int>
     */
    private function ids(Builder $products): array
    {
        return array_values(array_map(intval(...), $products->pluck(Product::COLUMN_ID)->all()));
    }
}
