<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\CategoryNode;
use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The active category tree for the storefront menus (roadmap D17: cached, invalidated by model events).
 * A category is shown only when it and every category above it are active.
 *
 * @phpstan-type CategoryRow array{id: int, name_fa: string, slug: string, children: list<mixed>}
 */
final class CategoryTree
{
    public const string CACHE_KEY = 'catalog:category-tree';

    /**
     * @return list<CategoryNode> top-level categories in their sort order
     */
    public function roots(): array
    {
        /** @var list<CategoryRow> $rows */
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());

        return array_map(self::node(...), $rows);
    }

    /**
     * Called by Category model events after any save, delete or restore.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  CategoryRow  $row
     */
    private static function node(array $row): CategoryNode
    {
        return new CategoryNode($row['id'], $row['name_fa'], $row['slug'], array_map(self::node(...), $row['children']));
    }

    /**
     * Plain arrays, because the cache does not unserialize objects (config cache.serializable_classes).
     *
     * @return list<CategoryRow>
     */
    private function build(): array
    {
        $byParent = Category::query()
            ->active()
            ->orderBy(Category::COLUMN_SORT)
            ->orderBy(Category::COLUMN_ID)
            ->get([Category::COLUMN_ID, Category::COLUMN_PARENT_ID, Category::COLUMN_NAME_FA, Category::COLUMN_SLUG])
            ->groupBy(fn (Category $category): int => $category->getParentId() ?? 0);

        return $this->rowsUnder($byParent, 0, 1);
    }

    /**
     * @param  Collection<int, EloquentCollection<int, Category>>  $byParent  categories grouped by parent id (0 = top level)
     * @return list<CategoryRow>
     */
    private function rowsUnder(Collection $byParent, int $parentKey, int $depth): array
    {
        if ($depth > Category::MAX_DEPTH) {
            return [];
        }

        $rows = [];

        foreach ($byParent->get($parentKey, new EloquentCollection) as $category) {
            $rows[] = [
                'id' => $category->getId(),
                'name_fa' => $category->getNameFa(),
                'slug' => $category->getSlug(),
                'children' => $this->rowsUnder($byParent, $category->getId(), $depth + 1),
            ];
        }

        return $rows;
    }
}
