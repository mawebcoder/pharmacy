<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Livewire;

use App\Modules\Catalog\Data\FacetGroup;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Data\ListingResult;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Queries\ProductListing as ProductListingQuery;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The product list of the category, brand, tag and search pages (roadmap B9.3): filters with counts, applied
 * filter chips, sort, and a grid that loads 20 more products as the shopper scrolls near its end, on every screen
 * (owner, 2026-11-27; replaces the numbered pages and «نمایش بیشتر» of S12). Filters live in the URL so lists can be shared.
 * <livewire:catalog::product-listing :category-id="$category->getId()" />
 *
 * @property-read ListingResult $result
 */
final class ProductListing extends Component
{
    public const int PER_PAGE = 20;

    public const array DISCOUNT_STEPS = [10, 20, 30, 50];

    #[Locked]
    public ?int $categoryId = null;

    #[Locked]
    public ?int $subcategoryId = null;

    #[Locked]
    public ?int $brandId = null;

    #[Locked]
    public ?int $tagId = null;

    #[Locked]
    public ?int $collectionId = null;

    #[Locked]
    public ?string $query = null;

    /**
     * Route name for product links with {searchLog}, {product}, {slug} (search results record clicks), or null.
     */
    #[Locked]
    public ?string $productRoute = null;

    /** @var array<string, int|string> extra route parameters for $productRoute */
    #[Locked]
    public array $productRouteParameters = [];

    /** @var list<int> */
    #[Url(as: 'brand', except: [])]
    public array $brands = [];

    /**
     * Attribute filters: attribute code => value ids. Not "$attributes", which Livewire uses itself. Typed loosely
     * because the values come from the address bar; read them through selectedAttributeValues().
     *
     * @var array<string, mixed>
     */
    #[Url(as: 'f', except: [])]
    public array $filters = [];

    /** Toman, as typed by the shopper. */
    #[Url(as: 'min')]
    public ?int $minPrice = null;

    #[Url(as: 'max')]
    public ?int $maxPrice = null;

    #[Url(as: 'stock')]
    public bool $inStock = false;

    #[Url(as: 'off')]
    public ?int $discount = null;

    /**
     * Products with a live flash deal (S28): where the home page row's «مشاهده همه» lands, with every filter. Not in the
     * filter panel (owner, 2026-11-17); shown as a chip that clears it.
     */
    #[Url(as: 'deal')]
    public bool $flashDeal = false;

    #[Url(except: '')]
    public string $sort = '';

    /** How many products are shown: PER_PAGE more each time the shopper scrolls near the end. */
    public int $shown = self::PER_PAGE;

    /** The list's address without query, for the crawlable «صفحه بعد» link (roadmap S38). */
    #[Locked]
    public string $listUrl = '';

    /** Pages a ?page=N address may open at once (N × PER_PAGE products). */
    public const int MAX_CRAWL_PAGES = 50;

    /**
     * ?page=N opens the list with its first N pages (search engines follow «صفحه بعد»; shoppers scroll instead).
     */
    public function mount(): void
    {
        $this->listUrl = request()->url();
        $page = max(1, min(self::MAX_CRAWL_PAGES, (int) request()->query('page', '1')));
        $this->shown = $page * self::PER_PAGE;
    }

    /**
     * The next page's address for search engines, only for the unfiltered list (filtered ones are not indexed).
     */
    public function nextPageUrl(): ?string
    {
        $nextPage = intdiv($this->shown, self::PER_PAGE) + 1;

        return $this->appliedFilters() === [] && $this->query === null && $this->sort === '' && $nextPage <= self::MAX_CRAWL_PAGES && $this->listUrl !== ''
            ? $this->listUrl.'?page='.$nextPage
            : null;
    }

    /**
     * A changed filter, sort or search starts the list again from its first page, and the page glides back to the top
     * of the list (owner, 2026-11-28; the view listens for "listing-filtered").
     */
    public function updated(string $property): void
    {
        if ($property !== 'shown') {
            $this->shown = self::PER_PAGE;
            unset($this->result);
            $this->dispatch('listing-filtered');
        }
    }

    public function loadMore(): void
    {
        if (! $this->hasMore()) {
            return;
        }

        $this->shown += self::PER_PAGE;
        unset($this->result);
    }

    public function removeBrand(int $brandId): void
    {
        $this->brands = array_values(array_diff($this->brands, [$brandId]));
        $this->updated('brands');
    }

    /**
     * Checks or unchecks an attribute value. Not wire:model: a group that has no key yet would be set to true
     * instead of getting the value id. A group left empty is dropped so the address stays clean.
     */
    public function toggleAttributeValue(string $code, int $valueId): void
    {
        $selected = $this->selectedAttributeValues($code);

        $this->setAttributeValues($code, in_array($valueId, $selected, true)
            ? array_values(array_diff($selected, [$valueId]))
            : [...$selected, $valueId]);
    }

    public function removeAttributeValue(string $code, int $valueId): void
    {
        $this->setAttributeValues($code, array_values(array_diff($this->selectedAttributeValues($code), [$valueId])));
    }

    /**
     * @return list<int>
     */
    public function selectedAttributeValues(string $code): array
    {
        $selected = $this->filters[$code] ?? [];

        return is_array($selected) ? array_values(array_map(intval(...), $selected)) : [];
    }

    public function clearFilters(): void
    {
        $this->reset('brands', 'filters', 'minPrice', 'maxPrice', 'inStock', 'discount', 'flashDeal');
        $this->updated('filters');
    }

    /**
     * Whether another PER_PAGE can load: the last load came back full and the total says there is more. A search index
     * that counts products it can no longer return (stale) must not keep the shopper loading nothing.
     */
    public function hasMore(): bool
    {
        $products = $this->result->products;

        return $products->count() >= $this->shown && $products->count() < $products->total();
    }

    #[Computed]
    public function result(): ListingResult
    {
        return app(ProductListingQuery::class)->get($this->criteria());
    }

    public function sortOrder(): ListingSort
    {
        $sorts = ListingSort::available($this->query !== null, $this->collectionId !== null);

        return ListingSort::tryFrom($this->sort) instanceof ListingSort && in_array(ListingSort::from($this->sort), $sorts, true)
            ? ListingSort::from($this->sort)
            : match (true) {
                $this->query !== null => ListingSort::Relevant,
                $this->collectionId !== null => ListingSort::Featured,
                default => ListingSort::Newest,
            };
    }

    /**
     * Applied filters as removable chips: [label, wire:click action].
     *
     * @return list<array{label: string, action: string}>
     */
    public function appliedFilters(): array
    {
        $chips = [];

        foreach ($this->result->facets as $group) {
            foreach ($group->options as $option) {
                if ($option->selected) {
                    $chips[] = [
                        'label' => $option->label,
                        'action' => $group->key === 'brand' ? "removeBrand({$option->id})" : "removeAttributeValue('{$group->key}', {$option->id})",
                    ];
                }
            }
        }

        $toman = fn (int $value): string => PersianDigits::toPersian(number_format($value));

        if ($this->minPrice !== null) {
            $chips[] = ['label' => 'از '.$toman($this->minPrice).' تومان', 'action' => '$set(\'minPrice\', null)'];
        }

        if ($this->maxPrice !== null) {
            $chips[] = ['label' => 'تا '.$toman($this->maxPrice).' تومان', 'action' => '$set(\'maxPrice\', null)'];
        }

        if ($this->discount !== null) {
            $chips[] = ['label' => PersianDigits::toPersian($this->discount).'٪ تخفیف و بیشتر', 'action' => '$set(\'discount\', null)'];
        }

        foreach (['inStock' => 'فقط موجود', 'flashDeal' => 'پیشنهاد لحظه‌ای'] as $property => $label) {
            if ($this->{$property}) {
                $chips[] = ['label' => $label, 'action' => "\$set('{$property}', false)"];
            }
        }

        return $chips;
    }

    public function productHref(Product $product): ?string
    {
        return $this->productRoute === null
            ? null
            : route($this->productRoute, [...$this->productRouteParameters, 'product' => $product->getId(), 'slug' => $product->getSlug()]);
    }

    /**
     * @return list<FacetGroup>
     */
    public function facetGroups(): array
    {
        return $this->result->facets;
    }

    public function render(): View
    {
        return view('catalog::livewire.product-listing', [
            'result' => $this->result,
            'sorts' => ListingSort::available($this->query !== null, $this->collectionId !== null),
            'currentSort' => $this->sortOrder(),
        ]);
    }

    /**
     * @param  list<int>  $valueIds
     */
    private function setAttributeValues(string $code, array $valueIds): void
    {
        if ($valueIds === []) {
            unset($this->filters[$code]);
        } else {
            $this->filters[$code] = $valueIds;
        }

        $this->updated('filters');
    }

    /**
     * @return array<string, list<int>>
     */
    private function selectedAttributes(): array
    {
        $selected = [];

        foreach (array_keys($this->filters) as $code) {
            $valueIds = $this->selectedAttributeValues((string) $code);

            if ($valueIds !== []) {
                $selected[(string) $code] = $valueIds;
            }
        }

        return $selected;
    }

    private function criteria(): ListingCriteria
    {
        return new ListingCriteria(
            query: $this->query,
            categoryId: $this->categoryId,
            subcategoryId: $this->subcategoryId,
            brandId: $this->brandId,
            tagId: $this->tagId,
            collectionId: $this->collectionId,
            brandIds: array_map(intval(...), $this->brands),
            attributeValueIds: $this->selectedAttributes(),
            minPrice: $this->minPrice === null ? null : Toman::toRial($this->minPrice),
            maxPrice: $this->maxPrice === null ? null : Toman::toRial($this->maxPrice),
            inStock: $this->inStock,
            minDiscount: in_array($this->discount, self::DISCOUNT_STEPS, true) ? $this->discount : null,
            flashDeal: $this->flashDeal,
            sort: $this->sortOrder(),
            page: 1,
            perPage: $this->shown,
        );
    }
}
