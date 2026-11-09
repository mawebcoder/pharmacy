<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The brands, categories and tags a home widget chose, in the admin's order, with their images; hidden ones are
 * skipped, at most 6 (roadmap D3.6, S13b). Also the collection a rail shows.
 */
final readonly class HomeItems
{
    /**
     * @param  list<int>  $ids
     * @return Collection<int, Brand>
     */
    public function brands(array $ids): Collection
    {
        return $this->inOrder(Brand::query()->active(), $ids);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Category>
     */
    public function categories(array $ids): Collection
    {
        return $this->inOrder(Category::query()->active(), $ids);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Tag>
     */
    public function tags(array $ids): Collection
    {
        return $this->inOrder(Tag::query(), $ids);
    }

    public function collection(int $id): ?ProductCollection
    {
        return ProductCollection::query()->visible()->find($id);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    private function inOrder(Builder $query, array $ids): Collection
    {
        $ids = array_slice($ids, 0, CollectionProducts::WIDGET_LIMIT);
        $found = $query->with('media')->whereKey($ids)->get()->keyBy(fn (Model $model): int => (int) $model->getKey());

        return new Collection(array_values(array_filter(array_map(fn (int $id): ?Model => $found->get($id), $ids))));
    }
}
