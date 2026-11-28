<?php

declare(strict_types=1);

use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Catalog\Queries\SearchSuggestionsQuery;
use App\Modules\Catalog\Queries\SearchVocabulary;
use App\Modules\Catalog\Search\ProductDocument;
use App\Modules\Catalog\Support\SearchQueryText;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Search\Filament\Pages\SearchInsights;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Search\Models\SearchSynonym;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/**
 * The searches the shop must answer the same way (roadmap S39 regression list): query => the product found first.
 */
const EXPECTED_FIRST = [
    'کرم سینره' => 'کرم مرطوب‌کننده سینره',
    'قیمت کرم سینره' => 'کرم مرطوب‌کننده سینره',
    'cinere' => 'کرم مرطوب‌کننده سینره',
    'كرم سينره' => 'کرم مرطوب‌کننده سینره',
    'سینیره' => 'کرم مرطوب‌کننده سینره',
    'ضد آفتاب' => 'کرم ضدآفتاب SPF50',
    'ضدآفتاب' => 'کرم ضدآفتاب SPF50',
    'ویتامین د ۱۰۰۰' => 'ویتامین D3 ۱۰۰۰ واحد',
    'IRC-778899' => 'ویتامین D3 ۱۰۰۰ واحد',
    // Misspelt English (owner, 2026-11-28): a missing, doubled or swapped letter still finds the cream.
    'cream' => 'کرم مرطوب‌کننده سینره',
    'crem' => 'کرم مرطوب‌کننده سینره',
    'creem' => 'کرم مرطوب‌کننده سینره',
    'craem' => 'کرم مرطوب‌کننده سینره',
    'cinere craem' => 'کرم مرطوب‌کننده سینره',
    // The same mistakes in Persian: a missing, doubled or swapped letter.
    'مرطب کننده' => 'کرم مرطوب‌کننده سینره',
    'سیرنه' => 'کرم مرطوب‌کننده سینره',
    'سینرره' => 'کرم مرطوب‌کننده سینره',
    'مرطوبکنند' => 'کرم مرطوب‌کننده سینره',
    'شامپو کودم' => 'شامپو کودک',
    'کمر سینره' => 'کرم مرطوب‌کننده سینره',
];

function tuningProduct(string $name, ?Brand $brand = null, array $attributes = []): Product
{
    $product = Product::factory()->active()->create([Product::COLUMN_NAME_FA => $name, ...($brand === null ? [] : [Product::COLUMN_BRAND_ID => $brand->getId()]), ...$attributes]);
    ProductVariant::factory()->for($product)->create();

    return $product;
}

describe('with the search index', function (): void {
    beforeEach(function (): void {
        useSearchIndex();
        $cinere = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره', Brand::COLUMN_NAME_EN => 'Cinere']);
        tuningProduct('کرم مرطوب‌کننده سینره', $cinere, [Product::COLUMN_NAME_EN => 'Cinere Moisturizing Cream']);
        tuningProduct('کرم ضدآفتاب SPF50');
        tuningProduct('ویتامین D3 ۱۰۰۰ واحد', attributes: [Product::COLUMN_IRC_CODE => 'IRC-778899']);
        tuningProduct('شامپو کودک');
        indexProductsForSearch();
    });

    it('finds the expected product first for every query of the regression list', function (string $query, string $first): void {
        $found = app(ProductListing::class)->get(new ListingCriteria(query: $query, sort: ListingSort::Relevant));

        expect($found->products->getCollection()->first()?->getNameFa())->toBe($first);
    })->with(array_map(null, array_keys(EXPECTED_FIRST), array_values(EXPECTED_FIRST)));

    it('finds tags in the search panel, with typos', function (): void {
        Tag::factory()->create([Tag::COLUMN_NAME_FA => 'مولتی ویتامین']);
        waitForSearchIndex();

        expect(app(SearchSuggestionsQuery::class)->for('مولتی ویتامن')->tags->map->getNameFa()->all())->toBe(['مولتی ویتامین']);
    });

    it('suggests the closest catalog word when a search finds nothing', function (): void {
        $this->get('/search?q=شامپوو کودکک بچه‌گانه')->assertOk();
        // Two letters off: too far for the search engine's typo rules at this length, close enough for the suggestion.
        $this->get('/search?q=سینیرهه')->assertOk()->assertSee('منظورتان')->assertSee('«سینره»', false);
    });

    it('measures instant search speed', function (): void {
        $this->artisan('search:benchmark', ['--queries' => 10])->assertSuccessful()->expectsOutputToContain('10 searches');
    });
});

it('ignores the buying words the admin set, but keeps a search made only of them', function (): void {
    $clean = app(SearchQueryText::class);

    expect($clean->clean('قیمت کرم سینره اصل'))->toBe('کرم سینره')
        ->and($clean->clean('خرید'))->toBe('خرید');

    app(SaveStoreSettings::class)([StoreSettings::SEARCH_IGNORED_WORDS => ['کرم']]);

    expect(app(SearchQueryText::class)->clean('قیمت کرم سینره'))->toBe('قیمت سینره');
});

it('leaves known, short and numeric words alone when suggesting', function (): void {
    tuningProduct('لوسیون بدن کودک');
    $vocabulary = app(SearchVocabulary::class);

    expect($vocabulary->suggest('لاسیئن'))->toBe('لوسیون')
        ->and($vocabulary->suggest('لوسیون بدن'))->toBeNull()
        ->and($vocabulary->suggest('لپ ۵۰'))->toBeNull();
});

it('puts the IRC code into the product document as an exact code', function (): void {
    $product = tuningProduct('قطره', attributes: [Product::COLUMN_IRC_CODE => 'IRC-12345']);

    expect(ProductDocument::for($product->load(ProductDocument::RELATIONS))['codes'])->toContain('irc-12345');
});

it('fixes a misspelt search from the insights page as a synonym of the right word', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    tuningProduct('لوسیون بدن کودک');
    $log = (new SearchLog)->setQuery('لاسیئن')->setNormalizedQuery('لاسیین')->setResultsCount(0);
    $log->save();

    livewire(SearchInsights::class)
        ->callAction(TestAction::make('fixSpelling')->table($log), ['correct' => 'لوسیون'])
        ->assertHasNoActionErrors();

    expect(SearchSynonym::query()->sole()->getSynonyms())->toContain('لوسیون');
});
