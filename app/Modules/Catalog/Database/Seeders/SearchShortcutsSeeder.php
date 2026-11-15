<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Data\CategoryNode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Queries\CategoryTree;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Database\Seeder;

/**
 * Default categories and brands for the search panel (owner, 2026-11-15): shown in the empty box and when a search
 * finds nothing, until the admin picks their own in «تنظیمات فروشگاه» › «جستجو». Takes the first top-level
 * categories in menu order and the first active brands by name, up to StoreSettings::MAX_SEARCH_SHORTCUTS each.
 * A list the admin has already filled is left alone, and nothing is saved while there is nothing to pick.
 */
final class SearchShortcutsSeeder extends Seeder
{
    public function run(StoreSettings $settings, CategoryTree $tree, SaveStoreSettings $saveSettings): void
    {
        $values = [];

        if ($settings->searchCategoryIds() === []) {
            $categoryIds = array_map(fn (CategoryNode $root): int => $root->id, array_slice($tree->roots(), 0, StoreSettings::MAX_SEARCH_SHORTCUTS));

            if ($categoryIds !== []) {
                $values[StoreSettings::SEARCH_CATEGORY_IDS] = $categoryIds;
            }
        }

        if ($settings->searchBrandIds() === []) {
            /** @var list<int> $brandIds */
            $brandIds = Brand::query()->active()->orderBy(Brand::COLUMN_NAME_FA)->limit(StoreSettings::MAX_SEARCH_SHORTCUTS)->pluck(Brand::COLUMN_ID)->map(intval(...))->all();

            if ($brandIds !== []) {
                $values[StoreSettings::SEARCH_BRAND_IDS] = $brandIds;
            }
        }

        if ($values !== []) {
            $saveSettings($values);
        }
    }
}
