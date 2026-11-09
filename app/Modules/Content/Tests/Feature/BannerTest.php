<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Content\Actions\DeleteBanner;
use App\Modules\Content\Actions\SaveBanner;
use App\Modules\Content\Actions\SetPlaceBanners;
use App\Modules\Content\Data\BannerData;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\BannerPlacement;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\PlaceBanners;

/**
 * @param  list<Banner>  $banners  in display order
 */
function placeBanners(HomeWidget $slider, array $banners): void
{
    app(SetPlaceBanners::class)(BannerPlace::HomeWidget, $slider->getId(), array_map(fn (Banner $banner): int => $banner->getId(), $banners));
}

/**
 * @return list<string>
 */
function shownBannerTitles(HomeWidget $slider): array
{
    return app(PlaceBanners::class)->for(BannerPlace::HomeWidget, $slider->getId())->map->getTitle()->all();
}

function slider(): HomeWidget
{
    return HomeWidget::factory()->ofType(HomeWidgetType::Slider)->create();
}

describe('links', function (): void {
    it('links to the picked item by its current slug', function (BannerLinkType $type, Closure $makeItem, string $path): void {
        $banner = Banner::factory()->linkingTo($type, $makeItem()->getId())->create();

        expect($banner->linkUrl())->toBe(url($path));
    })->with([
        'category' => [BannerLinkType::Category, fn () => Category::factory()->create([Category::COLUMN_SLUG => 'skin']), '/categories/skin'],
        'collection' => [BannerLinkType::Collection, fn () => ProductCollection::factory()->create([ProductCollection::COLUMN_SLUG => 'summer']), '/collections/summer'],
        'brand' => [BannerLinkType::Brand, fn () => Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']), '/brands/eucerin'],
        'tag' => [BannerLinkType::Tag, fn () => Tag::factory()->create([Tag::COLUMN_SLUG => 'vitamin-d']), '/t/vitamin-d'],
        'product' => [BannerLinkType::Product, fn () => Product::factory()->active()->create([Product::COLUMN_SLUG => 'urea-cream']), '/products/urea-cream'],
    ]);

    it('links to a list page or an external address', function (BannerLinkType $type, ?string $url, string $expected): void {
        expect(Banner::factory()->linkingTo($type, url: $url)->create()->linkUrl())->toBe(url($expected));
    })->with([
        'categories list' => [BannerLinkType::CategoriesList, null, '/categories'],
        'collections list' => [BannerLinkType::CollectionsList, null, '/collections'],
        'brands list' => [BannerLinkType::BrandsList, null, '/brands'],
        'external' => [BannerLinkType::External, 'https://example.com/sale', 'https://example.com/sale'],
    ]);

    it('follows a slug change', function (): void {
        $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);
        $banner = Banner::factory()->linkingTo(BannerLinkType::Brand, $brand->getId())->create();

        $brand->setSlug('eucerin-de')->save();

        expect($banner->linkUrl())->toBe(url('/brands/eucerin-de'));
    });

    it('has no link when the picked item is hidden or gone', function (BannerLinkType $type, Closure $makeItem): void {
        expect(Banner::factory()->linkingTo($type, $makeItem()->getId())->create()->linkUrl())->toBeNull();
    })->with([
        'inactive brand' => [BannerLinkType::Brand, fn () => Brand::factory()->inactive()->create()],
        'draft product' => [BannerLinkType::Product, fn () => Product::factory()->create()],
        'ended collection' => [BannerLinkType::Collection, fn () => ProductCollection::factory()->ended()->create()],
        'deleted category' => [BannerLinkType::Category, fn () => tap(Category::factory()->create())->delete()],
    ]);
});

describe('saving', function (): void {
    it('refuses an external address that is not https', function (string $url): void {
        $data = BannerData::fromArray([Banner::COLUMN_TITLE => 'حراج', Banner::COLUMN_LINK_TYPE => 'external', Banner::COLUMN_EXTERNAL_URL => $url]);

        expect(fn () => app(SaveBanner::class)($data))->toThrow(BannerChangeRefused::class, 'https://');
    })->with(['http' => 'http://example.com', 'javascript' => 'javascript:alert(1)', 'not an address' => 'https://']);

    it('refuses a catalog target without its item', function (): void {
        $data = BannerData::fromArray([Banner::COLUMN_TITLE => 'اوسرین', Banner::COLUMN_LINK_TYPE => 'brand', Banner::COLUMN_LINK_ID => 999_999]);

        expect(fn () => app(SaveBanner::class)($data))->toThrow(BannerChangeRefused::class, 'مقصد بنر');
    });

    it('moves a banner to the trash and takes it out of every place', function (): void {
        $banner = Banner::factory()->create();
        placeBanners(slider(), [$banner]);
        placeBanners(slider(), [$banner]);

        app(DeleteBanner::class)($banner);

        expect($banner->fresh()->trashed())->toBeTrue()
            ->and(BannerPlacement::query()->count())->toBe(0);
    });
});

describe('places', function (): void {
    it('replaces the banners of a place in the new order', function (): void {
        $slider = slider();
        [$first, $second, $third] = Banner::factory()->count(3)->create();
        placeBanners($slider, [$first, $second]);

        placeBanners($slider, [$third, $first]);

        expect(app(PlaceBanners::class)->ids(BannerPlace::HomeWidget, $slider->getId()))->toBe([$third->getId(), $first->getId()]);
    });

    it('refuses more than 6 banners in a place', function (): void {
        $banners = Banner::factory()->count(7)->create()->all();

        expect(fn () => placeBanners(slider(), $banners))->toThrow(BannerChangeRefused::class);

        expect(BannerPlacement::query()->count())->toBe(0);
    });

    it('refuses a page place without its page', function (): void {
        expect(fn () => app(SetPlaceBanners::class)(BannerPlace::Brand, null, [Banner::factory()->create()->getId()]))
            ->toThrow(BannerChangeRefused::class);
    });

    it('keeps the banners of different sliders apart', function (): void {
        [$summer, $winter] = [slider(), slider()];
        placeBanners($summer, [Banner::factory()->create([Banner::COLUMN_TITLE => 'حراج تابستان'])]);

        expect(shownBannerTitles($summer))->toBe(['حراج تابستان'])
            ->and(shownBannerTitles($winter))->toBe([]);
    });

    it('shows only banners that are active, inside their schedule and lead somewhere', function (): void {
        $this->freezeTime();
        $slider = slider();
        placeBanners($slider, [
            Banner::factory()->create([Banner::COLUMN_TITLE => 'فعال']),
            Banner::factory()->create([Banner::COLUMN_TITLE => 'غیرفعال', Banner::COLUMN_IS_ACTIVE => false]),
            Banner::factory()->create([Banner::COLUMN_TITLE => 'هنوز نه', Banner::COLUMN_STARTS_AT => now()->addMinute()]),
            Banner::factory()->create([Banner::COLUMN_TITLE => 'تمام‌شده', Banner::COLUMN_ENDS_AT => now()]),
            Banner::factory()->linkingTo(BannerLinkType::Brand, Brand::factory()->inactive()->create()->getId())->create([Banner::COLUMN_TITLE => 'مقصد پنهان']),
        ]);

        expect(shownBannerTitles($slider))->toBe(['فعال']);
    });
});

describe('home page', function (): void {
    it('opens an external banner in a new tab and a catalog banner in the site', function (): void {
        $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);
        placeBanners(slider(), [
            Banner::factory()->linkingTo(BannerLinkType::External, url: 'https://example.com/sale')->create(),
            Banner::factory()->linkingTo(BannerLinkType::Brand, $brand->getId())->create(),
        ]);

        $html = $this->get(route('home'))->getContent();

        expect($html)->toMatch('#href="https://example.com/sale"\s+target="_blank" rel="noopener"#')
            ->and($html)->toMatch('#href="'.preg_quote(url('/brands/eucerin'), '#').'"\s+wire:navigate#');
    });

    it('shows one banner without slider controls', function (): void {
        placeBanners(slider(), [Banner::factory()->create()]);

        $this->get(route('home'))->assertDontSee('x-data="slider({ rewind: true })"', escape: false);
    });

    it('shows several banners as a slider', function (): void {
        placeBanners(slider(), Banner::factory()->count(2)->create()->all());

        $this->get(route('home'))->assertSee('x-data="slider({ rewind: true })"', escape: false);
    });
});

describe('catalog pages', function (): void {
    it('shows the banners at the top of a page', function (Closure $makePage): void {
        [$url, $place, $placeId] = $makePage();
        $banner = Banner::factory()->linkingTo(BannerLinkType::External, url: 'https://example.com/sale')->create([Banner::COLUMN_TITLE => 'حراج تابستان']);
        app(SetPlaceBanners::class)($place, $placeId, [$banner->getId()]);

        $this->get($url)
            ->assertOk()
            ->assertSee('aria-label="حراج تابستان (1 از 1)"', escape: false)
            ->assertSee('href="https://example.com/sale"', escape: false);
    })->with([
        'brands list' => [fn (): array => [route('brands.index'), BannerPlace::BrandsList, null]],
        'categories list' => [fn (): array => [route('categories.index'), BannerPlace::CategoriesList, null]],
        'collections list' => [fn (): array => [route('collections.index'), BannerPlace::CollectionsList, null]],
        'brand' => [fn (): array => [route('brands.show', ($brand = Brand::factory()->create())->getSlug()), BannerPlace::Brand, $brand->getId()]],
        'category' => [fn (): array => [route('category.show', ($category = Category::factory()->create())->getSlug()), BannerPlace::Category, $category->getId()]],
        'collection' => [fn (): array => [route('collections.show', ($collection = ProductCollection::factory()->create())->getSlug()), BannerPlace::Collection, $collection->getId()]],
        'tag' => [fn (): array => [route('tags.show', ($tag = Tag::factory()->create())->getSlug()), BannerPlace::Tag, $tag->getId()]],
    ]);
});
