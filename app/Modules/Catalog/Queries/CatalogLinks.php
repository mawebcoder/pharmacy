<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog items other modules link to by type and id (banners, home widgets; roadmap D3.6, S13b): the storefront
 * URL of a shown item, its admin label, and admin search. Types: category, collection, brand, tag, product. An item
 * that is missing or hidden has no URL, so the link is not shown.
 */
final readonly class CatalogLinks
{
    public const array TYPES = ['category', 'subcategory', 'collection', 'brand', 'tag', 'product'];

    public function url(string $type, int $id): ?string
    {
        $item = $this->shown($type)?->find($id);

        return match (true) {
            $item instanceof Category => $item->url(),
            $item instanceof Subcategory => $item->url(),
            $item instanceof ProductCollection => route('collections.show', $item->getSlug()),
            $item instanceof Brand => route('brands.show', $item->getSlug()),
            $item instanceof Tag => route('tags.show', $item->getSlug()),
            $item instanceof Product => route('products.show', $item->getSlug()),
            default => null,
        };
    }

    public function label(string $type, int $id): ?string
    {
        $item = $this->all($type)?->withoutGlobalScopes()->find($id);

        return $item === null ? null : $this->nameOf($item);
    }

    /**
     * Up to 20 matches by Persian or English name (and SKU for products), id => name.
     *
     * @return array<int, string>
     */
    public function search(string $type, string $term): array
    {
        $query = $this->all($type);

        if (! $query instanceof Builder) {
            return [];
        }

        $query->where(fn (Builder $match) => $match
            ->where('name_fa', 'like', "%{$term}%")
            ->orWhere('name_en', 'like', "%{$term}%")
            ->when($type === 'product', fn (Builder $products) => $products->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', "%{$term}%"))));

        $results = [];

        foreach ($query->limit(20)->get() as $item) {
            $results[(int) $item->getKey()] = $this->nameOf($item);
        }

        return $results;
    }

    /**
     * Shown items of a type: active ones, collections inside their schedule.
     *
     * @return Builder<Model>|null
     */
    private function shown(string $type): ?Builder
    {
        /** @var Builder<Model>|null $query */
        $query = match ($type) {
            'category' => Category::query()->active(),
            'subcategory' => Subcategory::query()->active()->with('category')->whereHas('category', fn (Builder $category) => $category->active()),
            'collection' => ProductCollection::query()->visible(),
            'brand' => Brand::query()->active(),
            'tag' => Tag::query(),
            'product' => Product::query()->active(),
            default => null,
        };

        return $query;
    }

    /**
     * @return Builder<Model>|null
     */
    private function all(string $type): ?Builder
    {
        /** @var Builder<Model>|null $query */
        $query = match ($type) {
            'category' => Category::query(),
            'subcategory' => Subcategory::query()->with('category'),
            'collection' => ProductCollection::query(),
            'brand' => Brand::query(),
            'tag' => Tag::query(),
            'product' => Product::query(),
            default => null,
        };

        return $query;
    }

    /**
     * A subcategory reads «دسته › زیردسته», so the admin can tell same-named ones apart.
     */
    private function nameOf(Model $item): string
    {
        $name = (string) $item->getAttribute('name_fa');

        return $item instanceof Subcategory && $item->getCategory() instanceof Category ? $item->getCategory()->getNameFa().' › '.$name : $name;
    }
}
