<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;

/**
 * What coupons need to know about the catalog (roadmap D3.4, S22): each product's brand and categories, and a
 * category with everything below it (a coupon on «مراقبت پوست» covers its subcategories too).
 */
final readonly class CouponCatalog
{
    /**
     * Brand and categories of each product: main and extra categories, and the categories of its subcategories.
     *
     * @param  list<int>  $productIds
     * @return array<int, array{brandId: int|null, categoryIds: list<int>}>
     */
    public function productGroups(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $groups = [];

        foreach (Product::query()->withTrashed()->with(['categories:id', 'subcategories:id,category_id'])->whereKey(array_values(array_unique($productIds)))->get() as $product) {
            $categoryIds = $product->getCategories()->map(fn (Category $category): int => $category->getId())->all();
            $categoryIds[] = $product->getPrimaryCategoryId();
            array_push($categoryIds, ...$product->getSubcategories()->map(fn (Subcategory $subcategory): int => $subcategory->getCategoryId())->all());

            $groups[$product->getId()] = [
                'brandId' => $product->getBrandId(),
                'categoryIds' => array_values(array_unique(array_filter($categoryIds))),
            ];
        }

        return $groups;
    }

    /**
     * The categories a coupon covers. A category covers its subcategories through productGroups(), which counts the
     * categories behind a product's subcategories (owner, 2026-11-15: subcategories in their own table).
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function withSubcategories(array $categoryIds): array
    {
        return array_values(array_unique($categoryIds));
    }
}
