<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Content\Actions\DeleteHomeWidget;
use App\Modules\Content\Actions\ReorderHomeWidgets;
use App\Modules\Content\Actions\SaveHomeWidget;
use App\Modules\Content\Actions\SetPlaceBanners;
use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Exceptions\HomeWidgetChangeRefused;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\BannerPlacement;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;

/**
 * @param  array<string, mixed>  $data
 */
function saveWidget(array $data, ?HomeWidget $widget = null): HomeWidget
{
    return app(SaveHomeWidget::class)(HomeWidgetData::fromArray($data), $widget);
}

function titledWidget(string $title, int $sort = 0): HomeWidget
{
    return HomeWidget::factory()->ofType(HomeWidgetType::Trust, title: $title)->create([HomeWidget::COLUMN_SORT => $sort]);
}

describe('saving', function (): void {
    it('puts a new widget at the end of the page', function (): void {
        titledWidget('اول', sort: 4);

        $widget = saveWidget([HomeWidget::COLUMN_TYPE => 'trust']);

        expect($widget->getSort())->toBe(5);
    });

    it('keeps the chosen brands in order and only the settings of the type', function (): void {
        [$eucerin, $cinere] = Brand::factory()->count(2)->create();

        $widget = saveWidget([HomeWidget::COLUMN_TYPE => 'brands', 'brand_ids' => [$cinere->getId(), $eucerin->getId()], 'collection_id' => 5]);

        expect($widget->getSettings())->toBe(['brand_ids' => [$cinere->getId(), $eucerin->getId()]]);
    });

    it('refuses a collection rail without its collection', function (): void {
        expect(fn (): HomeWidget => saveWidget([HomeWidget::COLUMN_TYPE => 'collection']))->toThrow(HomeWidgetChangeRefused::class);
    });

    it('refuses no items or more than 6', function (int $count): void {
        $ids = Brand::factory()->count($count)->create()->map->getId()->all();

        expect(fn (): HomeWidget => saveWidget([HomeWidget::COLUMN_TYPE => 'brands', 'brand_ids' => $ids]))->toThrow(HomeWidgetChangeRefused::class);
    })->with(['none' => 0, 'seven' => 7]);

    it('saves the drag order', function (): void {
        [$first, $second, $third] = [titledWidget('اول', 1), titledWidget('دوم', 2), titledWidget('سوم', 3)];

        app(ReorderHomeWidgets::class)([$third->getId(), $first->getId(), $second->getId()]);

        expect(HomeWidget::query()->live()->get()->map->getTitleFa()->all())->toBe(['سوم', 'اول', 'دوم']);
    });

    it('takes a deleted slider\'s banners off the page and keeps them in the library', function (): void {
        $slider = HomeWidget::factory()->ofType(HomeWidgetType::Slider)->create();
        $banner = Banner::factory()->create();
        app(SetPlaceBanners::class)(BannerPlace::HomeWidget, $slider->getId(), [$banner->getId()]);

        app(DeleteHomeWidget::class)($slider);

        expect(HomeWidget::query()->count())->toBe(0)
            ->and(BannerPlacement::query()->count())->toBe(0)
            ->and($banner->fresh()->trashed())->toBeFalse();
    });
});

describe('home page', function (): void {
    it('shows the live widgets in the admin order', function (): void {
        $this->freezeTime();
        titledWidget('دوم', 2);
        titledWidget('اول', 1);
        HomeWidget::factory()->ofType(HomeWidgetType::Trust, title: 'غیرفعال')->create([HomeWidget::COLUMN_IS_ACTIVE => false]);
        HomeWidget::factory()->ofType(HomeWidgetType::Trust, title: 'تمام‌شده')->create([HomeWidget::COLUMN_ENDS_AT => now()]);
        HomeWidget::factory()->ofType(HomeWidgetType::Trust, title: 'هنوز نه')->create([HomeWidget::COLUMN_STARTS_AT => now()->addMinute()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['اول', 'دوم'])
            ->assertDontSee('غیرفعال')
            ->assertDontSee('تمام‌شده')
            ->assertDontSee('هنوز نه');
    });

    it('leaves out widgets with nothing to show and then shows the welcome', function (): void {
        HomeWidget::factory()->ofType(HomeWidgetType::Collection, ['collection_id' => ProductCollection::factory()->create()->getId()], 'کالکشن خالی')->create();
        HomeWidget::factory()->ofType(HomeWidgetType::Brands, ['brand_ids' => [Brand::factory()->inactive()->create()->getId()]], 'برندهای خاموش')->create();
        HomeWidget::factory()->ofType(HomeWidgetType::Slider, title: 'اسلایدر بی‌بنر')->create();

        $this->get(route('home'))
            ->assertSee('ضمانت اصالت کالا')
            ->assertDontSee('کالکشن خالی')
            ->assertDontSee('برندهای خاموش')
            ->assertDontSee('اسلایدر بی‌بنر');
    });

    it('shows a welcome before any widget exists', function (): void {
        $this->get(route('home'))->assertOk()->assertSee('ضمانت اصالت کالا');
    });

    it('shows at most 6 brands in the chosen order', function (): void {
        $brands = collect(range(1, 7))->map(fn (int $number): Brand => Brand::factory()->create([Brand::COLUMN_NAME_FA => "برند شماره {$number}"]));
        HomeWidget::factory()->ofType(HomeWidgetType::Brands, ['brand_ids' => $brands->reverse()->map->getId()->values()->all()])->create();

        $this->get(route('home'))
            ->assertSeeInOrder(['برند شماره 7', 'برند شماره 6', 'برند شماره 2'])
            ->assertDontSee('برند شماره 1');
    });

    it('shows a collection rail with at most 6 products and a link to the collection', function (): void {
        $collection = ProductCollection::factory()->create([ProductCollection::COLUMN_SLUG => 'summer']);
        $products = collect(range(1, 7))->map(function (int $number): Product {
            $product = Product::factory()->active()->create([Product::COLUMN_NAME_FA => "محصول شماره {$number}"]);
            ProductVariant::factory()->for($product)->create();

            return $product;
        });
        app(AddProductsToCollection::class)($collection, $products->map->getId()->all());
        HomeWidget::factory()->ofType(HomeWidgetType::Collection, ['collection_id' => $collection->getId()])->create();

        $this->get(route('home'))
            ->assertSeeInOrder(['محصول شماره 1', 'محصول شماره 6'])
            ->assertDontSee('محصول شماره 7')
            ->assertSee(route('collections.show', 'summer'));
    });

    it('shows a slider widget with its banners', function (): void {
        $slider = HomeWidget::factory()->ofType(HomeWidgetType::Slider)->create();
        app(SetPlaceBanners::class)(BannerPlace::HomeWidget, $slider->getId(), [Banner::factory()->create([Banner::COLUMN_TITLE => 'حراج تابستان'])->getId()]);

        $this->get(route('home'))->assertSee('aria-label="حراج تابستان (1 از 1)"', escape: false);
    });
});

it('tells the admin why a live widget is not shown', function (Closure $makeWidget, ?string $reason): void {
    expect(app(HomeWidgetItems::class)->hiddenReason($makeWidget()))->toBe($reason);
})->with([
    'trust is always shown' => [fn () => HomeWidget::factory()->create(), null],
    'inactive' => [fn () => HomeWidget::factory()->create([HomeWidget::COLUMN_IS_ACTIVE => false]), 'غیرفعال'],
    'schedule ended' => [fn () => HomeWidget::factory()->create([HomeWidget::COLUMN_ENDS_AT => now()->subMinute()]), 'خارج از زمان‌بندی'],
    'slider without banners' => [fn () => HomeWidget::factory()->ofType(HomeWidgetType::Slider)->create(), 'هیچ بنر فعالی ندارد'],
    'collection without stock' => [fn () => HomeWidget::factory()->ofType(HomeWidgetType::Collection, ['collection_id' => ProductCollection::factory()->create()->getId()])->create(), 'کالکشن محصول موجودی ندارد'],
    'hidden collection' => [fn () => HomeWidget::factory()->ofType(HomeWidgetType::Collection, ['collection_id' => ProductCollection::factory()->inactive()->create()->getId()])->create(), 'کالکشن فعال نیست'],
    'inactive brands' => [fn () => HomeWidget::factory()->ofType(HomeWidgetType::Brands, ['brand_ids' => [Brand::factory()->inactive()->create()->getId()]])->create(), 'هیچ‌کدام از موارد انتخاب‌شده فعال نیست'],
]);
