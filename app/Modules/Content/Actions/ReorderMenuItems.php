<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order of a menu's links (drag and drop in «منوها»); that order is the menu's.
 */
final class ReorderMenuItems
{
    /**
     * @param  list<int>  $itemIds  in the new order
     */
    public function __invoke(int $menuId, array $itemIds): void
    {
        $positions = array_flip($itemIds);

        DB::transaction(function () use ($menuId, $positions): void {
            foreach (MenuItem::query()->where(MenuItem::COLUMN_MENU_ID, $menuId)->whereKey(array_keys($positions))->get() as $item) {
                $item->setSort($positions[$item->getId()] + 1)->save();
            }
        });
    }
}
