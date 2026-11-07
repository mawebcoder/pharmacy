<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;

/**
 * Checks the category tree as it would be after a change: no category under itself or its own subcategories,
 * every parent present (not deleted) and at most Category::MAX_DEPTH levels.
 */
final class CategoryPlacement
{
    /**
     * Key for a category that is being created and has no id yet.
     */
    public const int NEW_CATEGORY = 0;

    /**
     * @param  array<int, int|null>  $newParents  category id => its new parent id (null for the top level)
     * @param  array<int, string>  $newNames  names of categories not saved yet, by the key used in $newParents
     *
     * @throws CategoryChangeRefused
     */
    public static function assertAllowed(array $newParents, array $newNames = []): void
    {
        $categories = Category::query()->get([Category::COLUMN_ID, Category::COLUMN_PARENT_ID, Category::COLUMN_NAME_FA]);

        $parents = $categories->mapWithKeys(fn (Category $category): array => [$category->getId() => $category->getParentId()])->all();
        $names = $categories->mapWithKeys(fn (Category $category): array => [$category->getId() => $category->getNameFa()])->all();

        self::check(array_replace($parents, $newParents), array_replace($names, $newNames));
    }

    /**
     * @param  array<int, int|null>  $parents  the whole tree: category id => parent id
     * @param  array<int, string>  $names
     *
     * @throws CategoryChangeRefused
     */
    private static function check(array $parents, array $names): void
    {
        foreach ($parents as $id => $parentId) {
            $depth = 1;
            $seen = [$id => true];

            while ($parentId !== null) {
                if (isset($seen[$parentId])) {
                    throw CategoryChangeRefused::ownDescendant($names[$id] ?? '');
                }

                if (! array_key_exists($parentId, $parents)) {
                    throw CategoryChangeRefused::parentMissing();
                }

                $seen[$parentId] = true;
                $depth++;
                $parentId = $parents[$parentId];
            }

            if ($depth > Category::MAX_DEPTH) {
                throw CategoryChangeRefused::tooDeep($names[$id] ?? '');
            }
        }
    }
}
