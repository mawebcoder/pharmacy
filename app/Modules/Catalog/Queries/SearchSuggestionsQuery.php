<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\SearchSuggestions;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Suggestions for the instant search box (roadmap D21.2 item 6). Products come from Meilisearch (typo tolerant);
 * brands, categories and tags are short lists matched in memory by word prefixes («ویت» → «ویتامین‌ها»).
 * Results are cached for 10 minutes per normalised text.
 */
final readonly class SearchSuggestionsQuery
{
    public const int MIN_LENGTH = 2;

    public function __construct(private Client $meilisearch) {}

    public function for(string $text): SearchSuggestions
    {
        $normalized = SearchText::normalize($text);

        if (mb_strlen($normalized) < self::MIN_LENGTH) {
            return new SearchSuggestions(new Collection, new Collection, new Collection, new Collection);
        }

        /** @var array{products: list<int>, available: bool, brands: list<int>, categories: list<int>, tags: list<int>} $ids */
        $ids = Cache::remember('catalog:suggestions:'.md5($normalized), now()->addMinutes(10), fn (): array => [
            ...$this->productIds($normalized),
            'brands' => $this->matching($normalized, Brand::query()->active()->get(), fn (Brand $brand): string => $brand->getNameFa().' '.$brand->getNameEn()),
            'categories' => $this->matching($normalized, $this->visibleCategories(), fn (Category $category): string => $category->getNameFa().' '.$category->getNameEn()),
            'tags' => $this->matching($normalized, Tag::query()->get(), fn (Tag $tag): string => $tag->getNameFa().' '.$tag->getNameEn()),
        ]);

        return new SearchSuggestions(
            products: $this->inOrder(Product::query()->active()->with(ProductListing::CARD_RELATIONS)->whereKey($ids['products'])->get(), $ids['products']),
            brands: $this->inOrder(Brand::query()->whereKey($ids['brands'])->get(), $ids['brands']),
            categories: $this->inOrder(Category::query()->whereKey($ids['categories'])->get(), $ids['categories']),
            tags: $this->inOrder(Tag::query()->with('media')->whereKey($ids['tags'])->get(), $ids['tags']),
            productsAvailable: $ids['available'],
        );
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
                ->search($normalized, ['limit' => 6, 'attributesToRetrieve' => ['id']])
                ->toArray();

            return ['products' => array_map(fn (array $hit): int => (int) $hit['id'], $result['hits']), 'available' => true];
        } catch (ApiException|CommunicationException|ClientExceptionInterface $exception) {
            Log::warning('Product search unavailable for suggestions.', ['error' => $exception->getMessage()]);

            return ['products' => [], 'available' => false];
        }
    }

    /**
     * Ids of up to 3 items whose name words start with every query word.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $items
     * @param  callable(TModel): string  $name
     * @return list<int>
     */
    private function matching(string $normalized, Collection $items, callable $name): array
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
            ->take(3)
            ->map(fn (Model $item): int => (int) $item->getKey())
            ->all());
    }

    /**
     * Categories the storefront shows (from the cached tree), as models.
     *
     * @return Collection<int, Category>
     */
    private function visibleCategories(): Collection
    {
        $ids = [];
        $collect = function (array $nodes) use (&$collect, &$ids): void {
            foreach ($nodes as $node) {
                $ids[] = $node->id;
                $collect($node->children);
            }
        };
        $collect(app(CategoryTree::class)->roots());

        return Category::query()->whereKey($ids)->get();
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
