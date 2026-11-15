<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\SearchSuggestions;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Suggestions for the instant search box (roadmap D21.2 item 6). Products, brands, categories and subcategories come from their own
 * Meilisearch indexes in one multi-search (typo tolerant, with the synonyms; brands and categories since 2026-11-15),
 * and the categories and brands of the matched products follow the ones whose names match: «کرم» → «مراقبت پوست».
 * A category or brand row that finds nothing shows the admin's pinned ones (SearchShortcuts). Without Meilisearch,
 * or before the brand and category indexes exist, brands and categories are matched in memory by word prefixes
 * («ویت» → «ویتامین‌ها»). Tags are always matched in memory. The ids are cached for 10 minutes per normalised text.
 */
final readonly class SearchSuggestionsQuery
{
    public const int MIN_LENGTH = 2;

    public const int MAX_PRODUCTS = 6;

    public const int MAX_SHORTCUTS = 8;

    public const int MAX_TAGS = 3;

    public function __construct(private Client $meilisearch, private CategoryTree $tree, private SearchShortcuts $shortcuts) {}

    public function for(string $text): SearchSuggestions
    {
        $normalized = SearchText::normalize($text);

        if (mb_strlen($normalized) < self::MIN_LENGTH) {
            return new SearchSuggestions(new Collection, new Collection, new Collection, new Collection);
        }

        /** @var array{products: list<int>, available: bool, brands: list<int>, categories: list<int>, subcategories: list<int>, tags: list<int>} $ids */
        $ids = Cache::remember('catalog:suggestions:'.md5($normalized), now()->addMinutes(10), fn (): array => $this->ids($normalized));

        // Categories, then subcategories (owner, 2026-11-15: their own table), in one row of at most 8.
        /** @var Collection<int, Category|Subcategory> $categories */
        $categories = SearchShortcuts::inOrder(Category::query()->with('media')->whereKey($ids['categories'])->get(), $ids['categories'])
            ->concat(SearchShortcuts::inOrder(Subcategory::query()->with(['media', 'category'])->whereKey($ids['subcategories'])->get(), $ids['subcategories']))
            ->take(self::MAX_SHORTCUTS)
            ->values();
        /** @var Collection<int, Category|Subcategory> $pinnedCategories */
        $pinnedCategories = new Collection($this->shortcuts->categories()->all());
        $brands = SearchShortcuts::inOrder(Brand::query()->active()->with('media')->whereKey($ids['brands'])->get(), $ids['brands']);

        return new SearchSuggestions(
            products: $this->inOrder(Product::query()->active()->with(ProductListing::CARD_RELATIONS)->whereKey($ids['products'])->get(), $ids['products']),
            brands: $brands->isEmpty() ? $this->shortcuts->brands() : $brands,
            categories: $categories->isEmpty() ? $pinnedCategories : $categories,
            tags: $this->inOrder(Tag::query()->with('media')->whereKey($ids['tags'])->get(), $ids['tags']),
            productsAvailable: $ids['available'],
            pinnedCategories: $categories->isEmpty(),
            pinnedBrands: $brands->isEmpty(),
        );
    }

    /**
     * @return array{products: list<int>, available: bool, brands: list<int>, categories: list<int>, subcategories: list<int>, tags: list<int>}
     */
    private function ids(string $normalized): array
    {
        $found = $this->search($normalized);
        [$productCategories, $productBrands, $productSubcategories] = $this->categoriesAndBrandsOf($found['products']);

        return [
            'products' => $found['products'],
            'available' => $found['available'],
            'categories' => array_slice(array_values(array_intersect(array_unique([...$found['categories'], ...$productCategories]), $this->tree->visibleIds())), 0, self::MAX_SHORTCUTS),
            'subcategories' => array_slice(array_values(array_intersect(array_unique([...$found['subcategories'], ...$productSubcategories]), $this->tree->visibleSubcategoryIds())), 0, self::MAX_SHORTCUTS),
            'brands' => array_slice(array_values(array_unique([...$found['brands'], ...$productBrands])), 0, self::MAX_SHORTCUTS),
            'tags' => $this->matching($normalized, Tag::query()->get(), fn (Tag $tag): string => $tag->getNameFa().' '.$tag->getNameEn(), self::MAX_TAGS),
        ];
    }

    /**
     * Products, brands and categories from Meilisearch in one request; when that fails (e.g. the brand or category
     * index does not exist yet), products alone and brands and categories matched in memory.
     *
     * @return array{products: list<int>, available: bool, brands: list<int>, categories: list<int>, subcategories: list<int>}
     */
    private function search(string $normalized): array
    {
        if (config('scout.driver') === 'meilisearch') {
            $query = fn (Product|Brand|Category|Subcategory $model, int $limit): SearchQuery => (new SearchQuery)
                ->setIndexUid($model->indexableAs())
                ->setQuery($normalized)
                ->setLimit(max(1, $limit))
                ->setAttributesToRetrieve(['id']);

            try {
                /** @var array{results: list<array{hits: list<array{id: int}>}>} $response */
                $response = $this->meilisearch->multiSearch([
                    $query(new Product, self::MAX_PRODUCTS),
                    $query(new Brand, self::MAX_SHORTCUTS),
                    $query(new Category, self::MAX_SHORTCUTS),
                    // Room for subcategories under a hidden category, which are dropped afterwards.
                    $query(new Subcategory, self::MAX_SHORTCUTS * 2),
                ]);
                [$products, $brands, $categories, $subcategories] = array_map(
                    fn (array $result): array => array_map(fn (array $hit): int => (int) $hit['id'], $result['hits']),
                    $response['results'],
                );

                return ['products' => $products, 'available' => true, 'brands' => $brands, 'categories' => $categories, 'subcategories' => $subcategories];
            } catch (ApiException|CommunicationException|ClientExceptionInterface $exception) {
                Log::warning('Brand and category search unavailable for suggestions.', ['error' => $exception->getMessage()]);
            }
        }

        return [
            ...$this->productIds($normalized),
            'brands' => $this->matching($normalized, Brand::query()->active()->get(), fn (Brand $brand): string => $brand->getNameFa().' '.$brand->getNameEn(), self::MAX_SHORTCUTS),
            'categories' => $this->matching($normalized, Category::query()->whereKey($this->tree->visibleIds())->get(), fn (Category $category): string => $category->getNameFa().' '.$category->getNameEn(), self::MAX_SHORTCUTS),
            'subcategories' => $this->matching($normalized, Subcategory::query()->whereKey($this->tree->visibleSubcategoryIds())->get(), fn (Subcategory $subcategory): string => $subcategory->getNameFa().' '.$subcategory->getNameEn(), self::MAX_SHORTCUTS),
        ];
    }

    /**
     * @return array{products: list<int>, available: bool}
     */
    private function productIds(string $normalized): array
    {
        if (config('scout.driver') !== 'meilisearch') {
            return ['products' => [], 'available' => false];
        }

        try {
            /** @var array{hits: list<array{id: int}>} $result */
            $result = $this->meilisearch->index((new Product)->indexableAs())
                ->search($normalized, ['limit' => self::MAX_PRODUCTS, 'attributesToRetrieve' => ['id']])
                ->toArray();

            return ['products' => array_map(fn (array $hit): int => (int) $hit['id'], $result['hits']), 'available' => true];
        } catch (ApiException|CommunicationException|ClientExceptionInterface $exception) {
            Log::warning('Product search unavailable for suggestions.', ['error' => $exception->getMessage()]);

            return ['products' => [], 'available' => false];
        }
    }

    /**
     * The main categories, brands and main subcategories of the matched products, in the products' order.
     *
     * @param  list<int>  $productIds
     * @return array{0: list<int>, 1: list<int>, 2: list<int>}
     */
    private function categoriesAndBrandsOf(array $productIds): array
    {
        if ($productIds === []) {
            return [[], [], []];
        }

        $products = $this->inOrder(
            Product::query()->whereKey($productIds)->get([Product::COLUMN_ID, Product::COLUMN_PRIMARY_CATEGORY_ID, Product::COLUMN_PRIMARY_SUBCATEGORY_ID, Product::COLUMN_BRAND_ID]),
            $productIds,
        );

        return [
            array_values($products->map(fn (Product $product): int => $product->getPrimaryCategoryId())->all()),
            array_values($products->map(fn (Product $product): int => $product->getBrandId())->all()),
            array_values(array_filter($products->map(fn (Product $product): ?int => $product->getPrimarySubcategoryId())->all())),
        ];
    }

    /**
     * Ids of up to $limit items whose name words start with every query word.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $items
     * @param  callable(TModel): string  $name
     * @return list<int>
     */
    private function matching(string $normalized, Collection $items, callable $name, int $limit): array
    {
        $queryWords = explode(' ', $normalized);

        return array_values($items
            ->filter(function (Model $item) use ($queryWords, $name): bool {
                $words = explode(' ', SearchText::forIndex($name($item)));

                foreach ($queryWords as $queryWord) {
                    if (! collect($words)->contains(fn (string $word): bool => str_starts_with($word, $queryWord))) {
                        return false;
                    }
                }

                return true;
            })
            ->take($limit)
            ->map(fn (Model $item): int => (int) $item->getKey())
            ->all());
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $models
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    private function inOrder(Collection $models, array $ids): Collection
    {
        $byId = $models->keyBy(fn (Model $model): int => (int) $model->getKey());

        return new Collection(array_values(array_filter(array_map(fn (int $id): ?Model => $byId->get($id), $ids))));
    }
}
