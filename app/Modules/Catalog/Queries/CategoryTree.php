<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\CategoryNode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Support\Facades\Cache;

/**
 * The active categories and their active subcategories for the storefront menus (roadmap D17: cached, invalidated
 * by Category and Subcategory model events). Two levels (owner, 2026-11-15): subcategories live in their own table.
 *
 * @phpstan-type SubcategoryRow array{id: int, name_fa: string, slug: string}
 * @phpstan-type CategoryRow array{id: int, name_fa: string, slug: string, children: list<SubcategoryRow>}
 */
final class CategoryTree
{
    public const string CACHE_KEY = 'catalog:category-tree';

    /**
     * @return list<CategoryNode> categories in their sort order, each with its subcategories
     */
    public function roots(): array
    {
        /** @var list<CategoryRow> $rows */
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());

        return array_map(fn (array $row): CategoryNode => new CategoryNode(
            $row['id'],
            $row['name_fa'],
            $row['slug'],
            array_map(fn (array $child): CategoryNode => new CategoryNode($child['id'], $child['name_fa'], $child['slug'], [], $row['slug']), $row['children']),
        ), $rows);
    }

    /**
     * Ids of the categories the storefront shows.
     *
     * @return list<int>
     */
    public function visibleIds(): array
    {
        return array_map(fn (CategoryNode $node): int => $node->id, $this->roots());
    }

    /**
     * Ids of the subcategories the storefront shows (active, under a shown category).
     *
     * @return list<int>
     */
    public function visibleSubcategoryIds(): array
    {
        $ids = [];

        foreach ($this->roots() as $root) {
            foreach ($root->children as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Called by Category and Subcategory model events after any save, delete or restore.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Plain arrays, because the cache does not unserialize objects (config cache.serializable_classes).
     *
     * @return list<CategoryRow>
     */
    private function build(): array
    {
        $children = Subcategory::query()
            ->active()
            ->ordered()
            ->get([Subcategory::COLUMN_ID, Subcategory::COLUMN_CATEGORY_ID, Subcategory::COLUMN_NAME_FA, Subcategory::COLUMN_SLUG])
            ->groupBy(fn (Subcategory $subcategory): int => $subcategory->getCategoryId());

        return array_values(Category::query()
            ->active()
            ->ordered()
            ->get([Category::COLUMN_ID, Category::COLUMN_NAME_FA, Category::COLUMN_SLUG])
            ->map(fn (Category $category): array => [
                'id' => $category->getId(),
                'name_fa' => $category->getNameFa(),
                'slug' => $category->getSlug(),
                'children' => array_values($children->get($category->getId(), collect())->map(fn (Subcategory $subcategory): array => [
                    'id' => $subcategory->getId(),
                    'name_fa' => $subcategory->getNameFa(),
                    'slug' => $subcategory->getSlug(),
                ])->all()),
            ])
            ->all());
    }
}
