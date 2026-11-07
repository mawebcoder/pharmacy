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
use Livewire\WithPagination;

/**
 * The product list of the category, brand, tag and search pages (roadmap B9.3): filters with counts, applied
 * filter chips, sort, a 24-product grid, numbered pages on desktop and «نمایش بیشتر» on phones and tablets
 * (decided 2026-11-07 in S12). Filters live in the URL so lists can be shared.
 * <livewire:catalog::product-listing :category-id="$category->getId()" />
 *
 * @property-read ListingResult $result
 */
final class ProductListing extends Component
{
    use WithPagination;

    public const int PER_PAGE = 24;

    public const array DISCOUNT_STEPS = [10, 20, 30, 50];

    #[Locked]
    public ?int $categoryId = null;

    #[Locked]
    public ?int $brandId = null;

    #[Locked]
    public ?int $tagId = null;

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

    #[Url(as: 'expiry')]
    public bool $nearExpiry = false;

    #[Url(as: 'damaged')]
    public bool $damagedBox = false;

    #[Url(except: '')]
    public string $sort = '';

    /** How many products phones and tablets show after «نمایش بیشتر». */
    public int $shown = self::PER_PAGE;

    public function updated(string $property): void
    {
        if (! in_array($property, ['shown', 'paginators'], true) && ! str_starts_with($property, 'paginators')) {
            $this->resetPage();
            $this->shown = self::PER_PAGE;
            unset($this->result);
        }
    }

    public function loadMore(): void
    {
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
        $this->reset('brands', 'filters', 'minPrice', 'maxPrice', 'inStock', 'discount', 'nearExpiry', 'damagedBox');
        $this->updated('filters');
    }

    #[Computed]
    public function result(): ListingResult
    {
        return app(ProductListingQuery::class)->get($this->criteria());
    }

    public function sortOrder(): ListingSort
    {
        $sorts = ListingSort::available($this->query !== null);

        return ListingSort::tryFrom($this->sort) instanceof ListingSort && in_array(ListingSort::from($this->sort), $sorts, true)
            ? ListingSort::from($this->sort)
            : ($this->query !== null ? ListingSort::Relevant : ListingSort::Newest);
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

        foreach (['inStock' => 'فقط موجود', 'nearExpiry' => 'زودمصرف', 'damagedBox' => 'کج‌جعبه'] as $property => $label) {
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
            'sorts' => ListingSort::available($this->query !== null),
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
        $isDesktopPage = $this->shown === self::PER_PAGE;

        return new ListingCriteria(
            query: $this->query,
            categoryId: $this->categoryId,
            brandId: $this->brandId,
            tagId: $this->tagId,
            brandIds: array_map(intval(...), $this->brands),
            attributeValueIds: $this->selectedAttributes(),
            minPrice: $this->minPrice === null ? null : Toman::toRial($this->minPrice),
            maxPrice: $this->maxPrice === null ? null : Toman::toRial($this->maxPrice),
            inStock: $this->inStock,
            minDiscount: in_array($this->discount, self::DISCOUNT_STEPS, true) ? $this->discount : null,
            nearExpiry: $this->nearExpiry,
            damagedBox: $this->damagedBox,
            sort: $this->sortOrder(),
            page: $isDesktopPage ? $this->getPage() : 1,
            perPage: $this->shown,
        );
    }
}
