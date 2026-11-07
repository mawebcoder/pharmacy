<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Catalog\Support\CategoryPlacement;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new order and nesting from the admin tree (drag and drop). Nothing is saved if the result would break
 * the tree rules. Returns how many categories moved.
 */
final class MoveCategories
{
    /**
     * @param  list<CategoryPositionData>  $positions
     *
     * @throws CategoryChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(array $positions): int
    {
        $byId = [];

        foreach ($positions as $position) {
            $byId[$position->categoryId] = $position;
        }

        CategoryPlacement::assertAllowed(array_map(fn (CategoryPositionData $position): ?int => $position->parentId, $byId));

        $moved = DB::transaction(function () use ($byId): int {
            $moved = 0;

            foreach (Category::query()->whereKey(array_keys($byId))->get() as $category) {
                $position = $byId[$category->getId()];
                $category->setParentId($position->parentId)->setSort($position->sort);

                if ($category->isDirty()) {
                    $category->save();
                    $moved++;
                }
            }

            return $moved;
        });

        if ($moved > 0) {
            Category::query()->whereKey(array_keys($byId))->whereNull(Category::COLUMN_PARENT_ID)->get()
                ->each(fn (Category $root) => ProductSearchIndex::refreshCategory($root));
        }

        return $moved;
    }
}
