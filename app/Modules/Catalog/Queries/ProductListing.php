<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\FacetGroup;
use App\Modules\Catalog\Data\FacetOption;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Data\ListingResult;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Product lists for the category, brand, tag and search pages (roadmap B9.3, D21; Meilisearch decided 2026-11-07
 * in S12). One multi-search returns the page and the filter counts: each filter group with a choice gets its own
 * count query without that group's filter, so its other options still show what they would add.
 * When Meilisearch is unreachable the list falls back to the database: newest first, page scope and brands only,
 * no text search or counts (degraded).
 */
final readonly class ProductListing
{
    /**
     * Relations product cards need.
     */
    public const array CARD_RELATIONS = ['brand', 'media', 'variants'];

    public function __construct(private Client $meilisearch) {}

    public function get(ListingCriteria $criteria): ListingResult
    {
        if (config('scout.driver') !== 'meilisearch') {
            return $this->fromDatabase($criteria, degraded: false);
        }

        try {
            return $this->fromSearch($criteria);
        } catch (ApiException|CommunicationException|ClientExceptionInterface $exception) {
            Log::warning('Product search unavailable, listing from the database.', ['error' => $exception->getMessage()]);

            return $this->fromDatabase($criteria, degraded: true);
        }
    }

    private function fromSearch(ListingCriteria $criteria): ListingResult
    {
        $index = (new Product)->indexableAs();
        $text = $criteria->isSearch() ? SearchText::normalize((string) $criteria->query) : '';
        $groups = $this->filterGroups($criteria);
        $sort = $criteria->sort === ListingSort::Relevant && $text === '' ? ListingSort::Newest : $criteria->sort;

        $queries = [
            (new SearchQuery)
                ->setIndexUid($index)
                ->setQuery($text)
                ->setFilter($this->filtersExcept($criteria, $groups, null))
                ->setFacets(['brand_id', 'attribute_value_ids', 'price', 'in_stock', 'is_near_expiry', 'is_damaged_box'])
                ->setSort($sort->meilisearchSort())
                ->setPage(max(1, $criteria->page))
                ->setHitsPerPage(max(1, $criteria->perPage))
                ->setAttributesToRetrieve(['id']),
        ];
        $facetQueryFor = [];

        foreach (array_keys($groups) as $group) {
            $facetQueryFor[$group] = count($queries);
            $queries[] = (new SearchQuery)
                ->setIndexUid($index)
                ->setQuery($text)
                ->setFilter($this->filtersExcept($criteria, $groups, $group))
                ->setFacets([$group === 'brand' ? 'brand_id' : ($group === 'price' ? 'price' : 'attribute_value_ids')])
                ->setLimit(0);
        }

        /** @var array{results: list<array{hits: list<array{id: int}>, totalHits?: int, facetDistribution?: array<string, array<string, int>>, facetStats?: array<string, array{min: float, max: float}>}>} $response */
        $response = $this->meilisearch->multiSearch($queries);
        $results = $response['results'];
        $main = $results[0];
        $distributionFor = fn (string $group, string $facet): array => $results[$facetQueryFor[$group] ?? 0]['facetDistribution'][$facet] ?? $main['facetDistribution'][$facet] ?? [];

        $ids = array_map(fn (array $hit): int => (int) $hit['id'], $main['hits']);
        $priceStats = $results[$facetQueryFor['price'] ?? 0]['facetStats']['price'] ?? null;

        return new ListingResult(
            products: $this->page($ids, (int) ($main['totalHits'] ?? count($ids)), $criteria),
            facets: [
                ...$this->brandFacet($criteria, $distributionFor('brand', 'brand_id')),
                ...$this->attributeFacets($criteria, fn (string $code): array => $distributionFor('attribute:'.$code, 'attribute_value_ids')),
            ],
            priceFloor: $priceStats === null ? null : (int) $priceStats['min'],
            priceCeiling: $priceStats === null ? null : (int) $priceStats['max'],
            flagCounts: [
                'in_stock' => (int) ($main['facetDistribution']['in_stock']['true'] ?? 0),
                'near_expiry' => (int) ($main['facetDistribution']['is_near_expiry']['true'] ?? 0),
                'damaged_box' => (int) ($main['facetDistribution']['is_damaged_box']['true'] ?? 0),
            ],
            degraded: false,
        );
    }

    /**
     * Filter expressions by group; the scope of the page (category, brand, tag) is always applied.
     *
     * @return array<string, string>
     */
    private function filterGroups(ListingCriteria $criteria): array
    {
        $groups = [];

        if ($criteria->brandIds !== []) {
            $groups['brand'] = 'brand_id IN ['.implode(', ', $criteria->brandIds).']';
        }

        foreach ($criteria->attributeValueIds as $code => $valueIds) {
            if ($valueIds !== []) {
                $groups['attribute:'.$code] = 'attribute_value_ids IN ['.implode(', ', array_map(intval(...), $valueIds)).']';
            }
        }

        $price = array_filter([
            $criteria->minPrice === null ? null : 'price >= '.$criteria->minPrice,
            $criteria->maxPrice === null ? null : 'price <= '.$criteria->maxPrice,
        ]);

        if ($price !== []) {
            $groups['price'] = implode(' AND ', $price);
        }

        return $groups;
    }

    /**
     * @param  array<string, string>  $groups
     * @return list<non-empty-string>
     */
    private function filtersExcept(ListingCriteria $criteria, array $groups, ?string $except): array
    {
        /** @var list<non-empty-string> $filters */
        $filters = array_values(array_filter([
            $criteria->categoryId === null ? null : 'category_ids = '.$criteria->categoryId,
            $criteria->brandId === null ? null : 'brand_id = '.$criteria->brandId,
            $criteria->tagId === null ? null : 'tag_ids = '.$criteria->tagId,
            $criteria->inStock ? 'in_stock = true' : null,
            $criteria->minDiscount === null ? null : 'discount_pct >= '.$criteria->minDiscount,
            $criteria->nearExpiry ? 'is_near_expiry = true' : null,
            $criteria->damagedBox ? 'is_damaged_box = true' : null,
        ]));

        foreach ($groups as $group => $filter) {
            if ($group !== $except && $filter !== '') {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * @param  array<int|string, int>  $distribution  brand id => count
     * @return list<FacetGroup>
     */
    private function brandFacet(ListingCriteria $criteria, array $distribution): array
    {
        if ($criteria->brandId !== null || ($distribution === [] && $criteria->brandIds === [])) {
            return [];
        }

        $brands = Brand::query()->whereKey([...array_keys($distribution), ...$criteria->brandIds])->orderBy(Brand::COLUMN_NAME_FA)->get();

        return [new FacetGroup('brand', 'برند', array_values($brands->map(fn (Brand $brand): FacetOption => new FacetOption(
            $brand->getId(),
            $brand->getNameFa(),
            (int) ($distribution[$brand->getId()] ?? 0),
            in_array($brand->getId(), $criteria->brandIds, true),
        ))->all()))];
    }

    /**
     * @param  callable(string): array<int|string, int>  $distributionFor  attribute code => value id => count
     * @return list<FacetGroup>
     */
    private function attributeFacets(ListingCriteria $criteria, callable $distributionFor): array
    {
        $groups = [];

        $attributes = Attribute::query()
            ->where(Attribute::COLUMN_IS_FILTERABLE, true)
            ->with('values')
            ->orderBy(Attribute::COLUMN_SORT)
            ->get();

        foreach ($attributes as $attribute) {
            $distribution = $distributionFor($attribute->getCode());
            $selected = $criteria->attributeValueIds[$attribute->getCode()] ?? [];
            $options = $attribute->getValues()
                ->map(fn (AttributeValue $value): FacetOption => new FacetOption(
                    $value->getId(),
                    $value->getValueFa(),
                    (int) ($distribution[$value->getId()] ?? 0),
                    in_array($value->getId(), $selected, true),
                ))
                ->filter(fn (FacetOption $option): bool => $option->count > 0 || $option->selected)
                ->all();

            if ($options !== []) {
                $groups[] = new FacetGroup($attribute->getCode(), $attribute->getNameFa(), array_values($options));
            }
        }

        return $groups;
    }

    /**
     * @param  list<int>  $ids  in the order Meilisearch returned them
     * @return LengthAwarePaginator<int, Product>
     */
    private function page(array $ids, int $total, ListingCriteria $criteria): LengthAwarePaginator
    {
        $products = $this->withCardRelations(Product::query()->whereKey($ids))->get()->keyBy(Product::COLUMN_ID);
        $ordered = new Collection(array_values(array_filter(array_map(fn (int $id): ?Product => $products->get($id), $ids))));

        return new LengthAwarePaginator($ordered, $total, $criteria->perPage, max(1, $criteria->page), [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    private function fromDatabase(ListingCriteria $criteria, bool $degraded): ListingResult
    {
        $query = $this->withCardRelations(Product::query()->active())
            ->whereHas('variants', fn (Builder $variants) => $variants->where(ProductVariant::COLUMN_IS_ACTIVE, true))
            ->when($criteria->categoryId !== null, function (Builder $query) use ($criteria): void {
                $ids = Category::query()->find($criteria->categoryId)?->subtreeIds() ?? [$criteria->categoryId];
                $query->where(fn (Builder $inCategory) => $inCategory
                    ->whereIn(Product::COLUMN_PRIMARY_CATEGORY_ID, $ids)
                    ->orWhereHas('categories', fn (Builder $categories) => $categories->whereIn('categories.id', $ids)));
            })
            ->when($criteria->brandId !== null, fn (Builder $query) => $query->where(Product::COLUMN_BRAND_ID, $criteria->brandId))
            ->when($criteria->brandIds !== [], fn (Builder $query) => $query->whereIn(Product::COLUMN_BRAND_ID, $criteria->brandIds))
            ->when($criteria->tagId !== null, fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($criteria->tagId)))
            ->when($criteria->isSearch() && $degraded, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderByDesc(Product::COLUMN_CREATED_AT)
            ->orderByDesc(Product::COLUMN_ID);

        /** @var LengthAwarePaginator<int, Product> $page */
        $page = $query->paginate($criteria->perPage, page: max(1, $criteria->page));

        return new ListingResult($page, [], null, null, ['in_stock' => 0, 'near_expiry' => 0, 'damaged_box' => 0], $degraded);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function withCardRelations(Builder $query): Builder
    {
        return $query->with([
            'brand',
            'media',
            'variants' => fn ($variants) => $variants->where(ProductVariant::COLUMN_IS_ACTIVE, true),
        ]);
    }
}
