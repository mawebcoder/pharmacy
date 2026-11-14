<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;

/**
 * What coupons need to know about the catalog (roadmap D3.4, S20): each product's brand and categories, and a
 * category with everything below it (a coupon on «مراقبت پوست» covers its subcategories too).
 */
final readonly class CouponCatalog
{
    /**
     * Brand and categories (primary and extra) of each product.
     *
     * @param  list<int>  $productIds
     * @return array<int, array{brandId: int, categoryIds: list<int>}>
     */
    public function productGroups(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $groups = [];

        foreach (Product::query()->withTrashed()->with('categories:id')->whereKey(array_values(array_unique($productIds)))->get() as $product) {
            $categoryIds = $product->getCategories()->map(fn (Category $category): int => $category->getId())->all();
            $categoryIds[] = $product->getPrimaryCategoryId();

            $groups[$product->getId()] = [
                'brandId' => $product->getBrandId(),
                'categoryIds' => array_values(array_unique(array_filter($categoryIds))),
            ];
        }

        return $groups;
    }

    /**
     * The given categories and every category below them.
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function withSubcategories(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $ids = [];

        foreach (Category::query()->whereKey($categoryIds)->get() as $category) {
            array_push($ids, ...$category->subtreeIds());
        }

        return array_values(array_unique($ids));
    }
}
