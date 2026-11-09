<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Support\AdminOptionLabel;
use App\Modules\Shared\Support\PersianDigits;
use Illuminate\Database\Eloquent\Builder;

/**
 * Options for searchable admin selects, with each item's round image (S13b, decided 2026-11-07). Products are picked
 * by SKU: one option per SKU (key: the variant id) showing the product name and, below it, the SKU title, code and
 * id; matched by product name, SKU code or id. Use productIdsOfSkus() to save the products.
 * Types: sku, brand, category, collection, tag.
 */
final readonly class AdminOptions
{
    private const int LIMIT = 20;

    /**
     * @return array<int, string> id => option HTML
     */
    public function search(string $type, string $term): array
    {
        $term = trim($term);

        return match ($type) {
            'sku' => $this->skus($this->skuQuery()->where(fn (Builder $match) => $match
                ->where(ProductVariant::COLUMN_SKU, 'like', "%{$term}%")
                ->orWhere('product_variants.'.ProductVariant::COLUMN_TITLE_FA, 'like', "%{$term}%")
                ->when(ctype_digit($term), fn (Builder $ids) => $ids->orWhere('product_variants.id', (int) $term)->orWhere(ProductVariant::COLUMN_PRODUCT_ID, (int) $term))
                ->orWhereHas('product', fn (Builder $products) => $products
                    ->where(Product::COLUMN_NAME_FA, 'like', "%{$term}%")
                    ->orWhere(Product::COLUMN_NAME_EN, 'like', "%{$term}%")))
                ->limit(self::LIMIT)),
            default => $this->named($type, fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name_fa', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%"))
                ->limit(self::LIMIT)),
        };
    }

    /**
     * Option HTML for chosen ids (for showing the current value).
     *
     * @param  list<int|string>  $ids
     * @return array<int, string>
     */
    public function labels(string $type, array $ids): array
    {
        $ids = array_map(intval(...), $ids);

        return $type === 'sku'
            ? $this->skus($this->skuQuery()->whereKey($ids))
            : $this->named($type, fn (Builder $query) => $query->whereKey($ids));
    }

    /**
     * Every item of a type, id => Persian name, in name order (for selects over a whole list).
     *
     * @return array<int, string>
     */
    public function names(string $type): array
    {
        $query = match ($type) {
            'brand' => Brand::query(),
            'category' => Category::query(),
            'collection' => ProductCollection::query(),
            'tag' => Tag::query(),
            default => null,
        };

        /** @var array<int, string> $names */
        $names = $query?->orderBy('name_fa')->pluck('name_fa', 'id')->all() ?? [];

        return $names;
    }

    /**
     * Adds each item's round image to ready-made labels (e.g. category paths «مکمل‌ها › ویتامین‌ها»), keeping order.
     *
     * @param  array<int, string>  $labels  id => plain label
     * @return array<int, string> id => option HTML
     */
    public function withImages(string $type, array $labels): array
    {
        $images = $this->named($type, fn (Builder $query) => $query->whereKey(array_keys($labels)), imagesOnly: true);
        $options = [];

        foreach ($labels as $id => $label) {
            $options[$id] = str_replace('%LABEL%', e($label), $images[$id] ?? AdminOptionLabel::html(null, 0, '%LABEL%'));
        }

        return $options;
    }

    /**
     * The products of chosen SKUs, in the order chosen, without repeats.
     *
     * @param  list<int|string>  $variantIds
     * @return list<int>
     */
    public function productIdsOfSkus(array $variantIds): array
    {
        $productOf = ProductVariant::query()->withTrashed()->whereKey($variantIds)->pluck(ProductVariant::COLUMN_PRODUCT_ID, ProductVariant::COLUMN_ID);

        return array_values(array_unique(array_filter(array_map(fn (int|string $id): ?int => isset($productOf[(int) $id]) ? (int) $productOf[(int) $id] : null, $variantIds))));
    }

    /**
     * A product's default SKU id, to show a saved product in a SKU select.
     */
    public function defaultSkuOf(int $productId): ?int
    {
        return Product::query()->with('variants')->find($productId)?->getDefaultVariant()?->getId()
            ?? ProductVariant::query()->where(ProductVariant::COLUMN_PRODUCT_ID, $productId)->value(ProductVariant::COLUMN_ID);
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function skuQuery(): Builder
    {
        return ProductVariant::query()->with(['product.media']);
    }

    /**
     * @param  Builder<ProductVariant>  $query
     * @return array<int, string>
     */
    private function skus(Builder $query): array
    {
        $options = [];

        foreach ($query->get() as $variant) {
            $product = $variant->getProduct();
            $options[$variant->getId()] = AdminOptionLabel::html(
                $product?->getMainImage(),
                80,
                $product?->getNameFa() ?? '—',
                $variant->getTitleFa().' · '.$variant->getSku().' · #'.PersianDigits::toPersian($variant->getId()),
            );
        }

        return $options;
    }

    /**
     * @param  callable(Builder<covariant \Illuminate\Database\Eloquent\Model>): mixed  $scope
     * @param  bool  $imagesOnly  label placeholder «%LABEL%» instead of the name, for withImages()
     * @return array<int, string>
     */
    private function named(string $type, callable $scope, bool $imagesOnly = false): array
    {
        [$query, $image, $width] = match ($type) {
            'brand' => [Brand::query(), fn (Brand $brand): ?\App\Modules\Media\Contracts\StoredImage => $brand->getLogo(), 160],
            'category' => [Category::query(), fn (Category $category): ?\App\Modules\Media\Contracts\StoredImage => $category->getIcon(), 96],
            'collection' => [ProductCollection::query(), fn (ProductCollection $collection): ?\App\Modules\Media\Contracts\StoredImage => $collection->getIcon(), 96],
            'tag' => [Tag::query(), fn (Tag $tag): ?\App\Modules\Media\Contracts\StoredImage => $tag->getIcon(), 96],
            default => [null, null, 0],
        };

        if ($query === null || $image === null) {
            return [];
        }

        $scope($query);
        $options = [];

        foreach ($query->with('media')->get() as $item) {
            $options[(int) $item->getKey()] = AdminOptionLabel::html($image($item), $width, $imagesOnly ? '%LABEL%' : (string) $item->getAttribute('name_fa'));
        }

        return $options;
    }
}
