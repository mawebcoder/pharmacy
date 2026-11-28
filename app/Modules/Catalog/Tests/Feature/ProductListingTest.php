<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Livewire\ProductListing as ProductListingComponent;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ComponentAttributeBag;
use Meilisearch\Client;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    useSearchIndex();
});

/**
 * An active product with one variant: price in rial, optional sale price and stock. No box price unless given, so
 * the only discount is the sale price.
 */
function sellable(array $product = [], array $variant = []): Product
{
    $created = Product::factory()->active()->create($product);
    ProductVariant::factory()->for($created)->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => 1_000_000, ...$variant]);

    return $created->fresh();
}

/**
 * @return list<string>
 */
function listedNames(ListingCriteria $criteria): array
{
    return app(ProductListing::class)->get($criteria)->products->getCollection()->map(fn (Product $product): string => $product->getNameFa())->all();
}

describe('search', function (): void {
    beforeEach(function (): void {
        $eucerin = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_NAME_EN => 'Eucerin']);
        sellable([Product::COLUMN_NAME_FA => 'کرم مرطوب‌کننده اوره', Product::COLUMN_NAME_EN => 'UreaRepair Cream', Product::COLUMN_BRAND_ID => $eucerin->getId()], [ProductVariant::COLUMN_SKU => 'EUC-000101-01']);
        sellable([Product::COLUMN_NAME_FA => 'ویتامین D3 ۱۰۰۰ واحد', Product::COLUMN_NAME_EN => 'Vitamin D3 1000 IU']);
        sellable([Product::COLUMN_NAME_FA => 'شامپو ضدشوره', Product::COLUMN_NAME_EN => 'Anti-dandruff Shampoo']);
        indexProductsForSearch();
    });

    it('finds products by Persian name, English name, brand and SKU', function (string $query, string $expected): void {
        expect(listedNames(new ListingCriteria(query: $query, sort: ListingSort::Relevant)))->toContain($expected);
    })->with([
        'Persian name with Arabic letters' => ['كرم مرطوب', 'کرم مرطوب‌کننده اوره'],
        'English name' => ['urearepair', 'کرم مرطوب‌کننده اوره'],
        'brand' => ['اوسرین', 'کرم مرطوب‌کننده اوره'],
        'SKU' => ['EUC-000101', 'کرم مرطوب‌کننده اوره'],
        'units written differently' => ['ویتامین د 1000iu', 'ویتامین D3 ۱۰۰۰ واحد'],
        'a typo' => ['ویتامیم', 'ویتامین D3 ۱۰۰۰ واحد'],
        'half-space or none' => ['ضد شوره', 'شامپو ضدشوره'],
    ]);

    it('finds nothing for an unrelated text', function (): void {
        expect(listedNames(new ListingCriteria(query: 'هواپیما', sort: ListingSort::Relevant)))->toBe([]);
    });
});

it('keeps products that are not for sale out of the index', function (): void {
    sellable([Product::COLUMN_NAME_FA => 'محصول فعال']);
    Product::factory()->withVariants()->create([Product::COLUMN_NAME_FA => 'پیش‌نویس']);
    Product::factory()->active()->create([Product::COLUMN_NAME_FA => 'بدون تنوع']);
    sellable([Product::COLUMN_NAME_FA => 'داروی نسخه‌ای', Product::COLUMN_PRODUCT_TYPE => ProductType::Rx]);
    sellable([Product::COLUMN_NAME_FA => 'حذف‌شده'])->delete();
    indexProductsForSearch();

    expect(listedNames(new ListingCriteria))->toBe(['محصول فعال']);
});

it('lists a category with the products of its subcategories, main or extra', function (): void {
    $supplements = Category::factory()->create();
    $vitamins = Subcategory::factory()->of($supplements)->create();
    $lipsticks = Subcategory::factory()->create();
    sellable([Product::COLUMN_NAME_FA => 'ویتامین', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $vitamins->getId()]);
    sellable([Product::COLUMN_NAME_FA => 'رژ لب', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $lipsticks->getId()]);
    $extra = sellable([Product::COLUMN_NAME_FA => 'امگا ۳', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $lipsticks->getId()]);
    $extra->subcategories()->attach($vitamins);
    indexProductsForSearch();

    expect(listedNames(new ListingCriteria(categoryId: $supplements->getId())))->toEqualCanonicalizing(['ویتامین', 'امگا ۳']);
});

it('lists a subcategory with its products, main or extra, from search and from the database', function (bool $searchIsDown): void {
    $category = Category::factory()->create();
    [$vitamins, $minerals] = Subcategory::factory()->of($category)->count(2)->create();
    sellable([Product::COLUMN_NAME_FA => 'ویتامین', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $vitamins->getId()]);
    sellable([Product::COLUMN_NAME_FA => 'زینک', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $minerals->getId()]);
    sellable([Product::COLUMN_NAME_FA => 'مولتی', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $minerals->getId()])->subcategories()->attach($vitamins);
    indexProductsForSearch();

    if ($searchIsDown) {
        config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
        app()->forgetInstance(Client::class);
    }

    expect(listedNames(new ListingCriteria(subcategoryId: $vitamins->getId())))->toEqualCanonicalizing(['ویتامین', 'مولتی']);
})->with(['search' => [false], 'database' => [true]]);

it('lists the products of a brand and of a tag', function (): void {
    $brand = Brand::factory()->create();
    $tag = Tag::factory()->create();
    sellable([Product::COLUMN_NAME_FA => 'از این برند', Product::COLUMN_BRAND_ID => $brand->getId()]);
    sellable([Product::COLUMN_NAME_FA => 'با این برچسب'])->tags()->attach($tag);
    indexProductsForSearch();

    expect(listedNames(new ListingCriteria(brandId: $brand->getId())))->toBe(['از این برند'])
        ->and(listedNames(new ListingCriteria(tagId: $tag->getId())))->toBe(['با این برچسب']);
});

describe('filters', function (): void {
    beforeEach(function (): void {
        $this->eucerin = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'اوسرین']);
        $this->cinere = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره']);
        $this->form = Attribute::factory()->create([Attribute::COLUMN_CODE => 'form', Attribute::COLUMN_NAME_FA => 'شکل محصول']);
        $this->cream = AttributeValue::factory()->for($this->form)->create([AttributeValue::COLUMN_VALUE_FA => 'کرم']);
        $this->gel = AttributeValue::factory()->for($this->form)->create([AttributeValue::COLUMN_VALUE_FA => 'ژل']);

        sellable([Product::COLUMN_NAME_FA => 'کرم اوسرین', Product::COLUMN_BRAND_ID => $this->eucerin->getId()], [ProductVariant::COLUMN_PRICE => 4_000_000, ProductVariant::COLUMN_SALE_PRICE => 3_000_000, ProductVariant::COLUMN_IS_NEAR_EXPIRY => true, ProductVariant::COLUMN_EXPIRY_DATE => '2027-02-10'])
            ->attributeValues()->attach($this->cream);
        sellable([Product::COLUMN_NAME_FA => 'ژل اوسرین', Product::COLUMN_BRAND_ID => $this->eucerin->getId()], [ProductVariant::COLUMN_PRICE => 2_000_000, ProductVariant::COLUMN_STOCK_STATUS => StockStatus::OutOfStock])
            ->attributeValues()->attach($this->gel);
        $cinereCream = sellable([Product::COLUMN_NAME_FA => 'کرم سینره', Product::COLUMN_BRAND_ID => $this->cinere->getId()], [ProductVariant::COLUMN_PRICE => 1_000_000]);
        $cinereCream->attributeValues()->attach($this->cream);
        // A live flash deal (S28) at 10% off.
        FlashDeal::factory()->create([FlashDeal::COLUMN_VARIANT_ID => $cinereCream->variants()->sole()->getId(), FlashDeal::COLUMN_DEAL_PRICE => 900_000]);
        app(SyncFlashDealPrices::class)();
        indexProductsForSearch();
    });

    it('filters by brand, attribute, price, stock, discount, near expiry and flash deal', function (array $filters, array $expected): void {
        expect(listedNames(new ListingCriteria(...$filters)))->toEqualCanonicalizing($expected);
    })->with([
        'brand' => [fn (): array => ['brandIds' => [test()->eucerin->getId()]], ['کرم اوسرین', 'ژل اوسرین']],
        'attribute' => [fn (): array => ['attributeValueIds' => ['form' => [test()->cream->getId()]]], ['کرم اوسرین', 'کرم سینره']],
        'brand and attribute' => [fn (): array => ['brandIds' => [test()->eucerin->getId()], 'attributeValueIds' => ['form' => [test()->cream->getId()]]], ['کرم اوسرین']],
        'price range (rial)' => [fn (): array => ['minPrice' => 1_500_000, 'maxPrice' => 3_000_000], ['کرم اوسرین', 'ژل اوسرین']],
        'in stock' => [fn (): array => ['inStock' => true], ['کرم اوسرین', 'کرم سینره']],
        'discount' => [fn (): array => ['minDiscount' => 20], ['کرم اوسرین']],
        'near expiry' => [fn (): array => ['nearExpiry' => true], ['کرم اوسرین']],
        'flash deal' => [fn (): array => ['flashDeal' => true], ['کرم سینره']],
    ]);

    it('keeps flash deals and «زودمصرف» out of the filter panel, and lists only deals from the home row\'s link', function (): void {
        livewire(ProductListingComponent::class)
            ->assertDontSeeHtml('wire:model.live="flashDeal"')
            ->assertDontSeeHtml('wire:model.live="nearExpiry"')
            ->set('flashDeal', true)
            ->assertSee(['کرم سینره', 'پیشنهاد لحظه‌ای'])
            ->assertDontSee('کرم اوسرین');
    });

    it('offers the same list without filters when they leave nothing, and the shop when nothing was filtered', function (): void {
        $listing = livewire(ProductListingComponent::class)
            ->set('discount', 50)
            ->assertSee('کالایی با این مشخصات پیدا نشد')
            ->assertSee('نمایش همه کالاها')
            ->call('clearFilters')
            ->assertDontSee('کالایی با این مشخصات پیدا نشد');

        expect($listing->get('discount'))->toBeNull();

        livewire(ProductListingComponent::class, ['categoryId' => Category::factory()->create()->getId()])
            ->assertSee('بازگشت به فروشگاه')
            ->assertDontSee('نمایش همه کالاها');
    });

    it('checks and unchecks an attribute value from the filter panel', function (): void {
        $listing = livewire(ProductListingComponent::class)->call('toggleAttributeValue', 'form', $this->cream->getId());

        expect($listing->get('filters'))->toBe(['form' => [$this->cream->getId()]])
            ->and($listing->instance()->result->products->getCollection()->map->getNameFa()->all())->toEqualCanonicalizing(['کرم اوسرین', 'کرم سینره']);

        $listing->call('toggleAttributeValue', 'form', $this->cream->getId())->assertSet('filters', []);
    });

    it('counts each brand and value as if only its own group were not filtered', function (): void {
        $result = app(ProductListing::class)->get(new ListingCriteria(brandIds: [$this->eucerin->getId()], attributeValueIds: ['form' => [$this->cream->getId()]]));
        $groups = collect($result->facets)->keyBy('key');
        $counts = fn (string $group): array => collect($groups[$group]->options)->mapWithKeys(fn ($option): array => [$option->label => $option->count])->all();

        expect($result->products->total())->toBe(1)
            ->and($counts('brand'))->toBe(['اوسرین' => 1, 'سینره' => 1])
            ->and($counts('form'))->toBe(['کرم' => 1, 'ژل' => 1])
            ->and($result->flagCounts['in_stock'])->toBe(1);
    });

    it('sorts by price and discount', function (ListingSort $sort, array $expected): void {
        expect(listedNames(new ListingCriteria(sort: $sort)))->toBe($expected);
    })->with([
        'cheapest' => [ListingSort::Cheapest, ['کرم سینره', 'ژل اوسرین', 'کرم اوسرین']],
        'most expensive' => [ListingSort::MostExpensive, ['کرم اوسرین', 'ژل اوسرین', 'کرم سینره']],
    ]);

    it('puts the biggest discount first', function (): void {
        expect(listedNames(new ListingCriteria(sort: ListingSort::BiggestDiscount))[0])->toBe('کرم اوسرین');
    });
});

it('filters and sorts by the price of the default SKU, not the cheapest one', function (): void {
    $packs = sellable([Product::COLUMN_NAME_FA => 'قرص ۹۰ عددی'], [ProductVariant::COLUMN_PRICE => 3_000_000]);
    $thirty = ProductVariant::factory()->for($packs)->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => 1_000_000, ProductVariant::COLUMN_SORT => 2]);
    $packs->setDefaultVariantId($packs->variants()->where(ProductVariant::COLUMN_PRICE, 3_000_000)->sole()->getId())->save();
    sellable([Product::COLUMN_NAME_FA => 'قرص میانه'], [ProductVariant::COLUMN_PRICE => 2_000_000]);
    indexProductsForSearch();

    expect(listedNames(new ListingCriteria(sort: ListingSort::Cheapest)))->toBe(['قرص میانه', 'قرص ۹۰ عددی'])
        ->and(listedNames(new ListingCriteria(maxPrice: 1_500_000)))->toBe([])
        ->and($thirty->getSort())->toBe(2);
});

it('pages through results, 24 at a time', function (): void {
    foreach (range(1, 26) as $number) {
        sellable([Product::COLUMN_NAME_FA => "محصول {$number}"]);
    }
    indexProductsForSearch();

    $second = app(ProductListing::class)->get(new ListingCriteria(page: 2));

    expect($second->products->total())->toBe(26)
        ->and($second->products->count())->toBe(2)
        ->and(app(ProductListing::class)->get(new ListingCriteria(perPage: 48))->products->count())->toBe(26);
});

it('shows 20 products, loads 20 more as the shopper scrolls, and starts again at 20 after a filter change', function (): void {
    foreach (range(1, 25) as $number) {
        sellable([Product::COLUMN_NAME_FA => "محصول {$number}"]);
    }
    indexProductsForSearch();

    $listing = livewire(ProductListingComponent::class)
        ->assertViewHas('result', fn ($result): bool => $result->products->count() === 20)
        ->assertSeeHtml('$wire.loadMore()')
        ->assertDontSee('نمایش بیشتر')
        ->call('loadMore')
        ->assertViewHas('result', fn ($result): bool => $result->products->count() === 25)
        ->assertDontSeeHtml('$wire.loadMore()');

    $listing->set('inStock', true)->assertSet('shown', 20);
});

it('updates the index when a supplier price changes the selling price', function (): void {
    $product = sellable([Product::COLUMN_NAME_FA => 'کرم'], [ProductVariant::COLUMN_PRICE => 5_000_000]);
    indexProductsForSearch();

    // 450,000 toman × 1.10 (the brand's commission) = 495,000 toman, already a whole 1,000.
    $_ = app(SaveProviderPrice::class)(ProviderPriceData::fromArray([
        ProviderPrice::COLUMN_PROVIDER_ID => Provider::factory()->create()->getId(),
        ProviderPrice::COLUMN_VARIANT_ID => $product->variants()->sole()->getId(),
        ProviderPrice::COLUMN_COST => 4_500_000,
    ]));
    waitForSearchIndex();

    // Prices in the listing criteria are rial: only the new price is below 4,960,000.
    expect($product->variants()->sole()->getPrice())->toBe(4_950_000)
        ->and(listedNames(new ListingCriteria(maxPrice: 4_960_000)))->toBe(['کرم']);
});

it('falls back to the newest products from the database when search is down', function (): void {
    $subcategory = Subcategory::factory()->create();
    $category = $subcategory->category;
    sellable([Product::COLUMN_NAME_FA => 'قدیمی', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $subcategory->getId()]);
    $this->travel(1)->minutes();
    sellable([Product::COLUMN_NAME_FA => 'جدید', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $subcategory->getId()]);
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
    app()->forgetInstance(Client::class);

    $result = app(ProductListing::class)->get(new ListingCriteria(categoryId: $category->getId()));

    expect($result->degraded)->toBeTrue()
        ->and($result->products->getCollection()->map->getNameFa()->all())->toBe(['جدید', 'قدیمی']);
});

it('leaves the brand off product cards and out of their query', function (): void {
    $subcategory = Subcategory::factory()->create();
    $category = $subcategory->category;
    $brand = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'برند پنهان']);
    sellable([Product::COLUMN_NAME_FA => 'کرم', Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $subcategory->getId(), Product::COLUMN_BRAND_ID => $brand->getId()]);
    indexProductsForSearch();

    $product = app(ProductListing::class)->get(new ListingCriteria(categoryId: $category->getId()))->products->getCollection()->first();

    expect($product?->relationLoaded('brand'))->toBeFalse()
        ->and(view('catalog::components.product-card', ['product' => $product, 'href' => null, 'attributes' => new ComponentAttributeBag])->render())
        ->toContain('کرم')->not->toContain('برند پنهان');
});

it('renders a listing page in a fixed number of queries', function (int $products): void {
    $subcategory = Subcategory::factory()->of(Category::factory()->create([Category::COLUMN_SLUG => 'skin']))->create();
    foreach (range(1, $products) as $number) {
        sellable([Product::COLUMN_NAME_FA => "محصول {$number}", Product::COLUMN_PRIMARY_SUBCATEGORY_ID => $subcategory->getId()]);
    }
    indexProductsForSearch();

    DB::enableQueryLog();
    $this->get('/categories/skin')->assertOk()->assertSee("محصول {$products}");
    $queries = count(DB::getQueryLog());

    // The same for 3 and 20 products; the ceiling includes the layout on a cold cache (menus, trust badges and
    // counters since S29–S30).
    expect($queries)->toBeLessThan(50);
})->with([3, 20]);

it('glides back to the top of the list after a filter change, but not when more products load', function (): void {
    livewire(ProductListingComponent::class)
        ->call('loadMore')
        ->assertNotDispatched('listing-filtered')
        ->set('inStock', true)
        ->assertDispatched('listing-filtered')
        ->assertSeeHtml('x-on:listing-filtered.window');
});
