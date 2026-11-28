<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Livewire\ProductListing as ProductListingComponent;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Seo\Actions\SaveRedirect;
use App\Modules\Seo\Data\SeoRedirectData;
use App\Modules\Seo\Enums\RedirectSource;
use App\Modules\Seo\Exceptions\RedirectRefused;
use App\Modules\Seo\Models\SeoRedirect;
use Illuminate\Testing\TestResponse;

use function Pest\Livewire\livewire;

/**
 * @return list<array<string, mixed>>
 */
function jsonLd(TestResponse $response): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

function sellableProduct(string $slug, array $variant = []): Product
{
    $product = Product::factory()->active()->create([Product::COLUMN_SLUG => $slug, Product::COLUMN_NAME_FA => 'کرم اوره']);
    ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_PRICE => 2_500_000, ProductVariant::COLUMN_OFFICIAL_PRICE => 2_500_000, ...$variant]);

    return $product->fresh();
}

describe('head tags', function (): void {
    it('gives a product page Product and BreadcrumbList JSON-LD with its price in rial and its stock', function (): void {
        $product = sellableProduct('urea-cream', [ProductVariant::COLUMN_STOCK_STATUS => StockStatus::OutOfStock, ProductVariant::COLUMN_SKU => 'UREA-50']);

        $response = $this->get('/products/urea-cream')->assertOk();
        $blocks = collect(jsonLd($response))->keyBy('@type');

        expect($blocks['Product']['name'])->toBe('کرم اوره')
            ->and($blocks['Product']['sku'])->toBe('UREA-50')
            ->and($blocks['Product']['offers'])->toMatchArray(['priceCurrency' => 'IRR', 'price' => 2_500_000, 'availability' => 'https://schema.org/OutOfStock'])
            ->and(collect($blocks['BreadcrumbList']['itemListElement'])->last()['name'])->toBe('کرم اوره');
        $response->assertSee('<title>کرم اوره | هوتانا</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('products.show', $product->getSlug()).'">', false)
            ->assertSee('<meta property="og:type" content="product">', false);
    });

    it('leaves the offer out for prescription drugs, which are not sold online', function (): void {
        $rx = Product::factory()->active()->rx()->withVariants()->create([Product::COLUMN_SLUG => 'rx-drug']);

        $product = collect(jsonLd($this->get('/products/rx-drug')->assertOk()))->firstWhere('@type', 'Product');

        expect($product)->not->toHaveKey('offers')->and($rx->exists)->toBeTrue();
    });

    it('keeps a shopper\'s own pages and filtered lists out of search results, with the clean address as canonical', function (): void {
        $this->get('/cart')->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/search?q=کرم')->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get('/products?brand[]=1&sort=cheapest')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('products.index').'">', false);

        $this->get('/products?page=2')
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="'.route('products.index').'?page=2">', false);
    });

    it('describes the store on the home page', function (): void {
        $types = collect(jsonLd($this->get('/')->assertOk()))->pluck('@type')->all();

        expect($types)->toBe(['OnlineStore', 'WebSite']);
    });
});

describe('sitemap and robots', function (): void {
    it('lists the sections and the product files in the sitemap index', function (): void {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee([route('seo.sitemap.section', 'brands'), route('seo.sitemap.products', 1)], false);
    });

    it('lists only products for sale, and only active brands', function (): void {
        sellableProduct('on-sale');
        Product::factory()->withVariants()->create([Product::COLUMN_SLUG => 'draft-one', Product::COLUMN_STATUS => ProductStatus::Draft]);
        Brand::factory()->create([Brand::COLUMN_SLUG => 'shown-brand']);
        Brand::factory()->inactive()->create([Brand::COLUMN_SLUG => 'hidden-brand']);

        $this->get('/sitemaps/products-1.xml')->assertOk()->assertSee(route('products.show', 'on-sale'), false)->assertDontSee('draft-one');
        $this->get('/sitemaps/brands.xml')->assertOk()->assertSee('shown-brand')->assertDontSee('hidden-brand');
        $this->get('/sitemaps/unknown.xml')->assertNotFound();
    });

    it('reads products from the search index when search runs on Meilisearch', function (): void {
        useSearchIndex();
        sellableProduct('indexed-on-sale');
        Product::factory()->withVariants()->create([Product::COLUMN_SLUG => 'indexed-draft', Product::COLUMN_STATUS => ProductStatus::Draft]);
        indexProductsForSearch();

        $this->get('/sitemaps/products-1.xml')->assertOk()->assertSee('indexed-on-sale')->assertDontSee('indexed-draft');
    });

    it('closes everything to robots outside production, and only the private areas in production', function (): void {
        $this->get('/robots.txt')->assertOk()->assertSeeText("User-agent: *\nDisallow: /", false)->assertDontSee('Sitemap:');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()->assertSee(['Disallow: /admin', 'Disallow: /account', 'Sitemap: '.route('seo.sitemap')]);
    });
});

describe('redirects', function (): void {
    it('sends a product\'s old address to the new one with a 301 when its slug changes, keeping the query string', function (): void {
        $product = sellableProduct('old-name');

        $product->setSlug('new-name')->save();

        $this->get('/products/old-name?ref=sms')->assertStatus(301)->assertRedirect('/products/new-name?ref=sms');
        expect(SeoRedirect::query()->sole()->getSource())->toBe(RedirectSource::Slug);
    });

    it('drops the redirect when an old address is used again', function (): void {
        $product = sellableProduct('first');
        $product->setSlug('second')->save();
        $product->setSlug('first')->save();

        $this->get('/products/first')->assertOk();
    });

    it('sends old redirects straight to the newest address, without chains', function (): void {
        app(SaveRedirect::class)(new SeoRedirectData('/a', '/b', 301));
        app(SaveRedirect::class)(new SeoRedirectData('/b', '/c', 301));

        $this->get('/a')->assertRedirect('/c');
        expect(SeoRedirect::query()->where(SeoRedirect::COLUMN_FROM_PATH, '/a')->value(SeoRedirect::COLUMN_TO_PATH))->toBe('/c');
    });

    it('refuses a loop, the home page and the admin', function (string $from, string $to): void {
        app(SaveRedirect::class)(new SeoRedirectData('/x', '/y', 301));

        expect(fn () => app(SaveRedirect::class)(new SeoRedirectData($from, $to, 301)))->toThrow(RedirectRefused::class);
    })->with([
        'loop' => ['/y', '/x'],
        'home' => ['/', '/products'],
        'admin' => ['/admin/products', '/products'],
    ]);
});

describe('crawlable list pages', function (): void {
    beforeEach(function (): void {
        foreach (range(1, 25) as $number) {
            sellableProduct('product-'.$number);
        }
    });

    it('links the next page for search engines on the unfiltered list, and opens ?page=N with N pages', function (): void {
        $this->get('/products')->assertOk()->assertSee('href="'.route('products.index').'?page=2" rel="next"', false);

        $this->get('/products?page=2')->assertOk()->assertDontSee('rel="next"', false);
    });

    it('adds no next-page link to a filtered list', function (): void {
        livewire(ProductListingComponent::class)->set('inStock', true)->assertDontSeeHtml('rel="next"');
    });
});
