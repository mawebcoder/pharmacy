<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Catalog\Queries\SearchSuggestionsQuery;
use App\Modules\Search\Actions\SaveSearchSynonym;
use App\Modules\Search\Livewire\InstantSearch;
use App\Modules\Search\Models\SearchLog;
use Meilisearch\Client;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
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

it('logs searches without results and suggests other ways', function (): void {
    Tag::factory()->featured()->create([Tag::COLUMN_NAME_FA => 'آهن']);

    $this->get('/search?q=هواپیما')
        ->assertOk()
        ->assertSee('کالایی برای «هواپیما» پیدا نشد.')
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

it('suggests products, brands, categories and tags while typing', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'ویتابیوتیکس']);
    Category::factory()->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    Tag::factory()->create([Tag::COLUMN_NAME_FA => 'ویتامین D']);

    $suggestions = app(SearchSuggestionsQuery::class)->for('ویتا');

    expect($suggestions->products->map->getNameFa()->all())->toBe(['ویتامین D3 ۱۰۰۰ واحد'])
        ->and($suggestions->brands->map->getNameFa()->all())->toBe(['ویتابیوتیکس'])
        ->and($suggestions->categories->map->getNameFa()->all())->toBe(['ویتامین‌ها'])
        ->and($suggestions->tags->map->getNameFa()->all())->toBe(['ویتامین D']);
});

it('shows the default SKU price, struck-through box price and discount in the suggestions', function (): void {
    $this->vitamin->variants()->sole()->setOfficialPrice(1_250_000)->setPrice(1_200_000)->setSalePrice(1_100_000)->save();

    $html = livewire(InstantSearch::class)->set('value', 'ویتا')->html();

    expect($html)->toMatch('/ویتامین D3 ۱۰۰۰ واحد.*<del.*۱۲۵٬۰۰۰ تومان.*۱۲٪.*۱۱۰٬۰۰۰ تومان/su');
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
