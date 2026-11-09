<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Collections\Pages\ListCollections;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Filament\Resources\Banners\Pages\CreateBanner;
use App\Modules\Content\Filament\Resources\Banners\Pages\ListBanners;
use App\Modules\Content\Filament\Resources\HomeWidgets\Pages\ManageHomeWidgets;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\PlaceBanners;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Storage::fake('local');
    Storage::fake('media');
    $this->seed(RolesSeeder::class);
});

function actingAsAdmin(AdminRole $role): void
{
    test()->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole($role)->create(), 'admin');
}

describe('as a content editor', function (): void {
    beforeEach(function (): void {
        actingAsAdmin(AdminRole::ContentEditor);
    });

    it('creates a banner that leads to the product of the picked SKU', function (): void {
        $product = Product::factory()->active()->create();
        $sku = ProductVariant::factory()->for($product)->create();

        livewire(CreateBanner::class)
            ->fillForm([
                Banner::COLUMN_TITLE => 'حراج کرم',
                Banner::COLUMN_LINK_TYPE => BannerLinkType::Product->value,
                'link_sku_id' => $sku->getId(),
                Banner::IMAGE_DESKTOP => [testImage(1920, 600, 'jpg')],
                Banner::IMAGE_MOBILE => [testImage(800, 1000, 'jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Banner::query()->sole())
            ->getLinkType()->toBe(BannerLinkType::Product)
            ->getLinkId()->toBe($product->getId())
            ->getDesktopImage()->not->toBeNull();
    });

    it('asks for the desktop and mobile artwork', function (): void {
        livewire(CreateBanner::class)
            ->fillForm([Banner::COLUMN_TITLE => 'حراج', Banner::COLUMN_LINK_TYPE => BannerLinkType::BrandsList->value])
            ->call('create')
            ->assertHasFormErrors([Banner::IMAGE_DESKTOP => 'required', Banner::IMAGE_MOBILE => 'required']);
    });

    it('adds a slider widget with its banners', function (): void {
        $banner = Banner::factory()->create();

        livewire(ManageHomeWidgets::class)
            ->callAction(CreateAction::class, [
                HomeWidget::COLUMN_TYPE => HomeWidgetType::Slider->value,
                'banner_ids' => [['banner_id' => $banner->getId()]],
            ])
            ->assertHasNoFormErrors();

        $slider = HomeWidget::query()->sole();
        expect(app(PlaceBanners::class)->ids(BannerPlace::HomeWidget, $slider->getId()))->toBe([$banner->getId()]);
    });

    it('shows on the widget list why a widget is not on the home page', function (): void {
        $slider = HomeWidget::factory()->ofType(HomeWidgetType::Slider)->create();

        livewire(ManageHomeWidgets::class)->assertTableColumnStateSet('on_page', 'هیچ بنر فعالی ندارد', $slider);
    });

    it('orders the home page by dragging the widgets', function (): void {
        [$first, $second] = HomeWidget::factory()->count(2)->sequence([HomeWidget::COLUMN_SORT => 1], [HomeWidget::COLUMN_SORT => 2])->create();

        livewire(ManageHomeWidgets::class)->call('reorderTable', [$second->getId(), $first->getId()]);

        expect(HomeWidget::query()->live()->pluck(HomeWidget::COLUMN_ID)->all())->toBe([$second->getId(), $first->getId()]);
    });
});

it('keeps an operator out of collections, banners and the home page builder', function (string $page): void {
    actingAsAdmin(AdminRole::Operator);

    livewire($page)->assertForbidden();
})->with([
    'collections' => ListCollections::class,
    'banners' => ListBanners::class,
    'home page builder' => ManageHomeWidgets::class,
]);
