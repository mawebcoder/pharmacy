<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Database\Seeders\PopularCollectionSeeder;
use App\Modules\Catalog\Database\Seeders\SearchShortcutsSeeder;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Catalog\Queries\SearchSuggestionsQuery;
use App\Modules\Identity\Models\Customer;
use App\Modules\Search\Actions\SaveSearchSynonym;
use App\Modules\Search\Livewire\InstantSearch;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use Meilisearch\Client;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Starting data the migrations no longer insert (owner, 2026-11-17).
    $this->seed(PopularCollectionSeeder::class);
    useSearchIndex();
    $this->vitamin = Product::factory()->active()->create([Product::COLUMN_NAME_FA => 'ویتامین D3 ۱۰۰۰ واحد', Product::COLUMN_SLUG => 'vitamin-d3']);
    ProductVariant::factory()->for($this->vitamin)->create();
    indexProductsForSearch();
});

it('finds products through synonyms', function (): void {
    $synonym = app(SaveSearchSynonym::class)('ویتامین دی', ['ویتامین d3', 'vitamin d']);
    waitForSearchIndex();

    $found = app(ProductListing::class)->get(new ListingCriteria(query: 'ویتامین دی', sort: ListingSort::Relevant));

    expect($synonym->getTerm())->toBe('ویتامین دی')
        ->and($found->products->getCollection()->map->getNameFa()->all())->toBe(['ویتامین D3 ۱۰۰۰ واحد']);
});

it('shows results on the search page and logs the search once', function (): void {
    $this->get('/search?q=ویتامین')
        ->assertOk()
        ->assertSee('نتایج جستجو برای «ویتامین»')
        ->assertSee('ویتامین D3 ۱۰۰۰ واحد');

    expect(SearchLog::query()->sole())
        ->getNormalizedQuery()->toBe('ویتامین')
        ->getResultsCount()->toBe(1);
});

it('puts a signed-in customer on their search log, so it becomes their history', function (): void {
    $customer = Customer::factory()->create();

    $this->get('/search?q=ویتامین')->assertOk();
    $this->actingAs($customer, 'customer')->get('/search?q=ویتامین')->assertOk();

    expect(SearchLog::query()->orderBy(SearchLog::COLUMN_ID)->get()->map->getCustomerId()->all())->toBe([null, $customer->getId()]);
});

it('logs searches without results and suggests other ways', function (): void {
    Tag::factory()->featured()->create([Tag::COLUMN_NAME_FA => 'آهن']);

    $this->get('/search?q=هواپیما')
        ->assertOk()
        ->assertSee('کالایی برای «هواپیما» پیدا نشد')
        ->assertSee('مشاهده همه دسته‌بندی‌ها')
        ->assertSee(route('categories.index'), false)
        ->assertSee('آهن');

    expect(SearchLog::query()->sole()->getResultsCount())->toBe(0);
});

it('records the product opened from the results', function (): void {
    $this->get('/search?q=ویتامین');
    $log = SearchLog::query()->sole();

    $this->get(route('search.open', ['searchLog' => $log->getId(), 'product' => $this->vitamin->getId(), 'slug' => 'vitamin-d3']))
        ->assertRedirect(route('products.show', 'vitamin-d3'));

    expect($log->fresh()->getClickedProductId())->toBe($this->vitamin->getId());
});

it('suggests products, then brands, categories and subcategories by name followed by those of the products, and tags', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'ویتابیوتیکس']);
    Category::factory()->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    Subcategory::factory()->create([Subcategory::COLUMN_NAME_FA => 'ویتامین C']);
    Tag::factory()->create([Tag::COLUMN_NAME_FA => 'ویتامین D']);
    waitForSearchIndex();

    $suggestions = app(SearchSuggestionsQuery::class)->for('ویتا');

    expect($suggestions->products->map->getNameFa()->all())->toBe(['ویتامین D3 ۱۰۰۰ واحد'])
        ->and($suggestions->brands->map->getNameFa()->all())->toBe(['ویتابیوتیکس', $this->vitamin->getBrand()->getNameFa()])
        // Categories first, then subcategories (owner, 2026-11-15), each by name and then the products' own.
        ->and($suggestions->categories->map->getNameFa()->all())->toBe(['ویتامین‌ها', $this->vitamin->getPrimaryCategory()->getNameFa(), 'ویتامین C', $this->vitamin->primarySubcategory->getNameFa()])
        ->and($suggestions->tags->map->getNameFa()->all())->toBe(['ویتامین D'])
        ->and($suggestions->pinnedBrands)->toBeFalse()
        ->and($suggestions->pinnedCategories)->toBeFalse();
});

it('finds brands and categories in their own indexes despite a typo, leaving out hidden ones', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'ویتابیوتیکس']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'ویتابیوتیکس قدیمی', Brand::COLUMN_IS_ACTIVE => false]);
    $archive = Category::factory()->create([Category::COLUMN_NAME_FA => 'آرشیو', Category::COLUMN_IS_ACTIVE => false]);
    Subcategory::factory()->of($archive)->create([Subcategory::COLUMN_NAME_FA => 'مکمل‌های گیاهی']);
    Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌های کودک', Category::COLUMN_IS_ACTIVE => false]);
    Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌های ورزشی']);
    waitForSearchIndex();

    expect(app(SearchSuggestionsQuery::class)->for('ویتابیوتکس')->brands->map->getNameFa()->all())->toBe(['ویتابیوتیکس'])
        ->and(app(SearchSuggestionsQuery::class)->for('مکمل')->categories->map->getNameFa()->all())->toBe(['مکمل‌های ورزشی']);
});

it('falls back to the pinned categories and brands, in the admin\'s order, when the text matches none', function (): void {
    $first = Category::factory()->create([Category::COLUMN_NAME_FA => 'مراقبت پوست']);
    $second = Category::factory()->create([Category::COLUMN_NAME_FA => 'مادر و کودک']);
    $hidden = Category::factory()->create([Category::COLUMN_IS_ACTIVE => false]);
    $brand = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'نیلوفر']);
    $inactive = Brand::factory()->create([Brand::COLUMN_IS_ACTIVE => false]);
    app(SaveStoreSettings::class)([
        StoreSettings::SEARCH_CATEGORY_IDS => [$second->getId(), $hidden->getId(), $first->getId()],
        StoreSettings::SEARCH_BRAND_IDS => [$inactive->getId(), $brand->getId()],
    ]);
    waitForSearchIndex();

    $suggestions = app(SearchSuggestionsQuery::class)->for('ژخپچ');

    expect($suggestions->isEmpty())->toBeTrue()
        ->and($suggestions->pinnedCategories)->toBeTrue()
        ->and($suggestions->categories->map->getNameFa()->all())->toBe(['مادر و کودک', 'مراقبت پوست'])
        ->and($suggestions->pinnedBrands)->toBeTrue()
        ->and($suggestions->brands->map->getNameFa()->all())->toBe(['نیلوفر']);

    livewire(InstantSearch::class)->set('value', 'ژخپچ')->assertSee('دسته‌بندی‌های پیشنهادی')->assertSee('برندهای پیشنهادی')->assertSee('مادر و کودک');
    livewire(InstantSearch::class)->set('popularRequested', true)->assertSee('مراقبت پوست')->assertSee('نیلوفر');
});

it('suggests at most 6 products', function (): void {
    foreach (range(1, 8) as $number) {
        $product = Product::factory()->active()->create([Product::COLUMN_NAME_FA => "ویتامین شماره {$number}"]);
        ProductVariant::factory()->for($product)->create();
    }
    indexProductsForSearch();

    expect(app(SearchSuggestionsQuery::class)->for('ویتامین')->products)->toHaveCount(SearchSuggestionsQuery::MAX_PRODUCTS)
        ->and(SearchSuggestionsQuery::MAX_PRODUCTS)->toBe(6);
});

it('pins the first categories and active brands by default, leaving the admin\'s choice alone', function (): void {
    $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
    $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
    $brand = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'آریا']);
    $inactive = Brand::factory()->create([Brand::COLUMN_IS_ACTIVE => false]);
    $settings = app(StoreSettings::class);

    $this->seed(SearchShortcutsSeeder::class);
    $seeded = [$settings->searchCategoryIds(), $settings->searchBrandIds()];
    app(SaveStoreSettings::class)([StoreSettings::SEARCH_CATEGORY_IDS => [$second->getId()]]);
    $this->seed(SearchShortcutsSeeder::class);

    [$categories, $brands] = $seeded;

    expect(array_search($first->getId(), $categories, true))->toBeLessThan(array_search($second->getId(), $categories, true))
        ->and($brands)->toContain($brand->getId())->not->toContain($inactive->getId())
        ->and($settings->searchCategoryIds())->toBe([$second->getId()]);
});

it('matches brands and categories in memory when Meilisearch is off', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'ویتابیوتیکس']);
    Category::factory()->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    config(['scout.driver' => 'null']);

    $suggestions = app(SearchSuggestionsQuery::class)->for('ویتا');

    expect($suggestions->productsAvailable)->toBeFalse()
        ->and($suggestions->brands->map->getNameFa()->all())->toBe(['ویتابیوتیکس'])
        ->and($suggestions->categories->map->getNameFa()->all())->toBe(['ویتامین‌ها']);
});

it('suggests products as cards with the default SKU price, struck-through box price and discount, and a link to all results', function (): void {
    $this->vitamin->variants()->sole()->setOfficialPrice(1_250_000)->setPrice(1_200_000)->setSalePrice(1_100_000)->save();

    $html = livewire(InstantSearch::class)->set('value', 'ویتا')->html();

    expect($html)->toMatch('/<article.*ویتامین D3 ۱۰۰۰ واحد.*۱۱۰٬۰۰۰ تومان.*<del.*۱۲۵٬۰۰۰ تومان.*۱۲٪/su')
        ->toContain(e(route('search', ['q' => 'ویتا'])));
});

it('says search is unavailable when Meilisearch is down', function (): void {
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
    app()->forgetInstance(Client::class);

    $this->get('/search?q=ویتامین')->assertOk()->assertSee('جستجو موقتاً در دسترس نیست');

    expect(SearchLog::query()->count())->toBe(0);
});

describe('empty search box', function (): void {
    beforeEach(function (): void {
        $popular = ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->sole();
        app(AddProductsToCollection::class)($popular, [$this->vitamin->getId()]);
    });

    it('shows «محصولات محبوب» after the first focus', function (): void {
        livewire(InstantSearch::class)
            ->assertDontSee('ویتامین D3 ۱۰۰۰ واحد')
            ->set('popularRequested', true)
            ->assertSee('محصولات محبوب')
            ->assertSee('ویتامین D3 ۱۰۰۰ واحد')
            ->assertSee(route('collections.show', 'popular'));
    });

    it('hides the popular products while typing', function (): void {
        livewire(InstantSearch::class)
            ->set('popularRequested', true)
            ->set('value', 'شامپو')
            ->assertDontSee('محصولات محبوب');
    });
});
