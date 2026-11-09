<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\SaveProductVariant;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Livewire\ProductListing as ProductListingComponent;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Queries\ProductListing;
use Illuminate\Support\Facades\DB;
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
    sellable([Product::COLUMN_NAME_FA => 'داروی نسخه‌ای', Product::COLUMN_PRODUCT_TYPE => ProductType::Rx, Product::COLUMN_REQUIRES_PRESCRIPTION => true]);
    sellable([Product::COLUMN_NAME_FA => 'حذف‌شده'])->delete();
    indexProductsForSearch();

    expect(listedNames(new ListingCriteria))->toBe(['محصول فعال']);
});

it('lists a category with the products of its subcategories', function (): void {
    $supplements = Category::factory()->create();
    $vitamins = Category::factory()->childOf($supplements)->create();
    $beauty = Category::factory()->create();
    sellable([Product::COLUMN_NAME_FA => 'ویتامین', Product::COLUMN_PRIMARY_CATEGORY_ID => $vitamins->getId()]);
    sellable([Product::COLUMN_NAME_FA => 'رژ لب', Product::COLUMN_PRIMARY_CATEGORY_ID => $beauty->getId()]);
    $extra = sellable([Product::COLUMN_NAME_FA => 'امگا ۳', Product::COLUMN_PRIMARY_CATEGORY_ID => $beauty->getId()]);
    $extra->categories()->attach($supplements);
    indexProductsForSearch();

    expect(listedNames(new ListingCriteria(categoryId: $supplements->getId())))->toEqualCanonicalizing(['ویتامین', 'امگا ۳']);
});

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

        sellable([Product::COLUMN_NAME_FA => 'کرم اوسرین', Product::COLUMN_BRAND_ID => $this->eucerin->getId()], [ProductVariant::COLUMN_PRICE => 4_000_000, ProductVariant::COLUMN_SALE_PRICE => 3_000_000])
            ->attributeValues()->attach($this->cream);
        sellable([Product::COLUMN_NAME_FA => 'ژل اوسرین', Product::COLUMN_BRAND_ID => $this->eucerin->getId()], [ProductVariant::COLUMN_PRICE => 2_000_000, ProductVariant::COLUMN_STOCK_STATUS => StockStatus::OutOfStock])
            ->attributeValues()->attach($this->gel);
        sellable([Product::COLUMN_NAME_FA => 'کرم سینره', Product::COLUMN_BRAND_ID => $this->cinere->getId()], [ProductVariant::COLUMN_PRICE => 1_000_000, ProductVariant::COLUMN_IS_NEAR_EXPIRY => true])
            ->attributeValues()->attach($this->cream);
        indexProductsForSearch();
    });

    it('filters by brand, attribute, price, stock, discount and near expiry', function (array $filters, array $expected): void {
        expect(listedNames(new ListingCriteria(...$filters)))->toEqualCanonicalizing($expected);
    })->with([
        'brand' => [fn (): array => ['brandIds' => [test()->eucerin->getId()]], ['کرم اوسرین', 'ژل اوسرین']],
        'attribute' => [fn (): array => ['attributeValueIds' => ['form' => [test()->cream->getId()]]], ['کرم اوسرین', 'کرم سینره']],
        'brand and attribute' => [fn (): array => ['brandIds' => [test()->eucerin->getId()], 'attributeValueIds' => ['form' => [test()->cream->getId()]]], ['کرم اوسرین']],
        'price range (rial)' => [fn (): array => ['minPrice' => 1_500_000, 'maxPrice' => 3_000_000], ['کرم اوسرین', 'ژل اوسرین']],
        'in stock' => [fn (): array => ['inStock' => true], ['کرم اوسرین', 'کرم سینره']],
        'discount' => [fn (): array => ['minDiscount' => 20], ['کرم اوسرین']],
        'near expiry' => [fn (): array => ['nearExpiry' => true], ['کرم سینره']],
    ]);

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

it('updates the index when a variant price changes', function (): void {
    $product = sellable([Product::COLUMN_NAME_FA => 'کرم'], [ProductVariant::COLUMN_PRICE => 5_000_000]);
    indexProductsForSearch();

    $changed = app(SaveProductVariant::class)($product, ProductVariantData::fromArray([
        ProductVariant::COLUMN_TITLE_FA => 'تنوع',
        ProductVariant::COLUMN_PRICE => 500_000,
        ProductVariant::COLUMN_STOCK_STATUS => 'available',
    ]), $product->variants()->sole());
    waitForSearchIndex();

    expect($changed->getPrice())->toBe(500_000)
        ->and(listedNames(new ListingCriteria(maxPrice: 600_000)))->toBe(['کرم']);
});

it('falls back to the newest products from the database when search is down', function (): void {
    $category = Category::factory()->create();
    sellable([Product::COLUMN_NAME_FA => 'قدیمی', Product::COLUMN_PRIMARY_CATEGORY_ID => $category->getId()]);
    $this->travel(1)->minutes();
    sellable([Product::COLUMN_NAME_FA => 'جدید', Product::COLUMN_PRIMARY_CATEGORY_ID => $category->getId()]);
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
    app()->forgetInstance(Client::class);

    $result = app(ProductListing::class)->get(new ListingCriteria(categoryId: $category->getId()));

    expect($result->degraded)->toBeTrue()
        ->and($result->products->getCollection()->map->getNameFa()->all())->toBe(['جدید', 'قدیمی']);
});

it('renders a listing page in a fixed number of queries', function (int $products): void {
    $category = Category::factory()->create([Category::COLUMN_SLUG => 'skin']);
    foreach (range(1, $products) as $number) {
        sellable([Product::COLUMN_NAME_FA => "محصول {$number}", Product::COLUMN_PRIMARY_CATEGORY_ID => $category->getId()]);
    }
    indexProductsForSearch();

    DB::enableQueryLog();
    $this->get('/categories/skin')->assertOk()->assertSee("محصول {$products}");
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThan(40);
})->with([3, 20]);
