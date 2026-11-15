<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new order of the categories from the admin table (drag and drop). Returns how many categories moved.
 */
final class MoveCategories
{
    /**
     * @param  list<CategoryPositionData>  $positions
     */
    #[\NoDiscard]
    public function __invoke(array $positions): int
    {
        $sorts = [];

        foreach ($positions as $position) {
            $sorts[$position->categoryId] = $position->sort;
        }

        return DB::transaction(function () use ($sorts): int {
            $moved = 0;

            foreach (Category::query()->whereKey(array_keys($sorts))->get() as $category) {
                $category->setSort($sorts[$category->getId()]);

                if ($category->isDirty()) {
                    $category->save();
                    $moved++;
                }
            }

            return $moved;
        });
    }
}
