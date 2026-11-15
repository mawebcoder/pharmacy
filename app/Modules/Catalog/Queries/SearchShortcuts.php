<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The categories and brands the admin pins to the search panel in «تنظیمات فروشگاه» › «جستجو» (owner, 2026-11-15),
 * shown when the box is empty and when the text matches no category or brand. Only what the storefront shows is
 * returned (visible categories, active brands), in the admin's order, with their icons and logos.
 */
final readonly class SearchShortcuts
{
    public function __construct(private StoreSettings $settings, private CategoryTree $tree) {}

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        $ids = array_values(array_intersect($this->settings->searchCategoryIds(), $this->tree->visibleIds()));

        return self::inOrder(Category::query()->with('media')->whereKey($ids)->get(), $ids);
    }

    /**
     * @return Collection<int, Brand>
     */
    public function brands(): Collection
    {
        $ids = $this->settings->searchBrandIds();

        return self::inOrder(Brand::query()->active()->with('media')->whereKey($ids)->get(), $ids);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $models
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    public static function inOrder(Collection $models, array $ids): Collection
    {
        $byId = $models->keyBy(fn (Model $model): int => (int) $model->getKey());

        return new Collection(array_values(array_filter(array_map(fn (int $id): ?Model => $byId->get($id), $ids))));
    }
}
