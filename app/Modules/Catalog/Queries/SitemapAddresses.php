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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * The catalog's addresses for sitemap.xml (roadmap S38), only what shoppers can see. Products, brands, categories and
 * subcategories are read from their Meilisearch indexes (owner, 2026-11-27: faster than the database), filtered on
 * is_visible / is_active, through the documents API, which is not capped like search results; if Meilisearch cannot
 * answer, from the database with the same rules. Collections and tags have no index and come from the database.
 */
final readonly class SitemapAddresses
{
    public const array SECTIONS = ['categories', 'brands', 'tags', 'collections'];

    public const int PRODUCTS_PER_FILE = 5000;

    private const int PAGE = 1000;

    public function __construct(private CategoryTree $tree, private Client $meilisearch) {}

    /**
     * Number of product files (at least one, so the index always lists products).
     */
    public function productFiles(): int
    {
        $count = $this->fromIndex(
            fn (): int => $this->meilisearch->index((new Product)->indexableAs())
                ->getDocuments((new DocumentsQuery)->setFilter(['is_visible = true'])->setFields(['id'])->setLimit(1))
                ->getTotal(),
            fn (): int => Product::query()->sellable()->count(),
        );

        return max(1, (int) ceil($count / self::PRODUCTS_PER_FILE));
    }

    /**
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    public function products(int $file): array
    {
        $offset = (max(1, $file) - 1) * self::PRODUCTS_PER_FILE;

        return $this->fromIndex(
            fn (): array => array_map(
                fn (array $document): array => ['loc' => route('products.show', $document['slug']), 'lastmod' => self::time($document['updated_at'] ?? 0)],
                $this->documents(new Product, ['is_visible = true'], ['id', 'slug', 'updated_at'], $offset, self::PRODUCTS_PER_FILE),
            ),
            fn (): array => array_values(Product::query()->sellable()
                ->orderBy(Product::COLUMN_ID)
                ->forPage(max(1, $file), self::PRODUCTS_PER_FILE)
                ->get([Product::COLUMN_ID, Product::COLUMN_SLUG, Product::COLUMN_UPDATED_AT])
                ->map(fn (Product $product): array => ['loc' => route('products.show', $product->getSlug()), 'lastmod' => $product->getUpdatedAt()])
                ->all()),
        );
    }

    /**
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    public function section(string $section): array
    {
        return match ($section) {
            'categories' => $this->categories(),
            'brands' => $this->brands(),
            'tags' => $this->of(Tag::query()->whereHas('products', fn (Builder $products) => $products->sellable()), fn (Tag $tag): string => route('tags.show', $tag->getSlug())),
            'collections' => $this->of(ProductCollection::query()->visible(), fn (ProductCollection $collection): string => route('collections.show', $collection->getSlug())),
            default => [],
        };
    }

    /**
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    private function brands(): array
    {
        return $this->fromIndex(
            fn (): array => array_map(
                fn (array $document): array => ['loc' => route('brands.show', $document['slug']), 'lastmod' => self::time($document['updated_at'] ?? 0)],
                $this->documents(new Brand, ['is_active = true'], ['id', 'slug', 'updated_at']),
            ),
            fn (): array => $this->of(Brand::query()->active(), fn (Brand $brand): string => route('brands.show', $brand)),
        );
    }

    /**
     * Shown categories and their shown subcategories (the cached CategoryTree decides, as on the storefront).
     *
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    private function categories(): array
    {
        $categoryIds = $this->tree->visibleIds();
        $subcategoryIds = $this->tree->visibleSubcategoryIds();

        return $this->fromIndex(
            fn (): array => [
                ...array_map(
                    fn (array $document): array => ['loc' => route('category.show', $document['slug']), 'lastmod' => self::time($document['updated_at'] ?? 0)],
                    array_values(array_filter($this->documents(new Category, ['is_active = true'], ['id', 'slug', 'updated_at']), fn (array $document): bool => in_array((int) $document['id'], $categoryIds, true))),
                ),
                ...array_map(
                    fn (array $document): array => ['loc' => route('subcategory.show', [$document['category_slug'], $document['slug']]), 'lastmod' => self::time($document['updated_at'] ?? 0)],
                    array_values(array_filter($this->documents(new Subcategory, ['is_active = true'], ['id', 'slug', 'category_slug', 'updated_at']), fn (array $document): bool => in_array((int) $document['id'], $subcategoryIds, true) && filled($document['category_slug'] ?? null))),
                ),
            ],
            fn (): array => [
                ...$this->of(Category::query()->whereKey($categoryIds), fn (Category $category): string => $category->url()),
                ...$this->of(Subcategory::query()->with('category')->whereKey($subcategoryIds), fn (Subcategory $subcategory): string => $subcategory->url()),
            ],
        );
    }

    /**
     * Documents of a model's index matching the filter, a page at a time (all of them when no limit is given).
     *
     * @param  non-empty-list<non-empty-string>  $filter
     * @param  non-empty-list<string>  $fields
     * @param  int<0, max>  $offset
     * @return list<array<string, mixed>>
     */
    private function documents(Product|Brand|Category|Subcategory $model, array $filter, array $fields, int $offset = 0, ?int $limit = null): array
    {
        $documents = [];
        $index = $this->meilisearch->index($model->indexableAs());

        do {
            $take = max(0, $limit === null ? self::PAGE : min(self::PAGE, $limit - count($documents)));
            $page = $index->getDocuments((new DocumentsQuery)->setFilter($filter)->setFields($fields)->setLimit($take)->setOffset($offset + count($documents)));
            $documents = [...$documents, ...$page->getResults()];
        } while (count($page->getResults()) === $take && ($limit === null || count($documents) < $limit));

        return $documents;
    }

    /**
     * The index answer, or the database one when search is not Meilisearch here or does not answer.
     *
     * @template T
     *
     * @param  callable(): T  $index
     * @param  callable(): T  $database
     * @return T
     */
    private function fromIndex(callable $index, callable $database): mixed
    {
        if (config('scout.driver') !== 'meilisearch') {
            return $database();
        }

        try {
            return $index();
        } catch (ApiException|CommunicationException|ClientExceptionInterface $exception) {
            Log::warning('Sitemap read from the database: search unavailable.', ['error' => $exception->getMessage()]);

            return $database();
        }
    }

    private static function time(mixed $timestamp): ?Carbon
    {
        return is_numeric($timestamp) && (int) $timestamp > 0 ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    /**
     * @template TModel of Brand|Tag|ProductCollection|Category|Subcategory
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): string  $url
     * @return list<array{loc: string, lastmod: ?Carbon}>
     */
    private function of(Builder $query, callable $url): array
    {
        $entries = [];

        foreach ($query->get() as $model) {
            /** @var TModel $model */
            $entries[] = ['loc' => $url($model), 'lastmod' => $model->getAttribute('updated_at')];
        }

        return $entries;
    }
}
