<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;

/**
 * Brings a category back from the trash. Its parent must be restored first, so the tree has no orphans.
 */
final class RestoreCategory
{
    /**
     * @throws CategoryChangeRefused
     */
    public function __invoke(Category $category): void
    {
        $parentId = $category->getParentId();

        if ($parentId !== null && ! Category::query()->whereKey($parentId)->exists()) {
            throw CategoryChangeRefused::parentDeleted($category->getNameFa());
        }

        $category->restore();
    }
}
