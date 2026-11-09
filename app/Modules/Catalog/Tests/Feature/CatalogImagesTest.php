<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Brands\Pages\CreateBrand;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Media\Actions\AttachImage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('media');
});

describe('admin forms', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    });

    it('uploads a brand logo with the brand', function (): void {
        livewire(CreateBrand::class)
            ->fillForm([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_NAME_EN => 'Eucerin', Brand::IMAGE_LOGO => testImage(800, 400)])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Brand::query()->sole()->getLogo()?->isReady())->toBeTrue();
    });

    it('rejects a logo that does not match the preset', function (): void {
        livewire(CreateBrand::class)
            ->fillForm([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::IMAGE_LOGO => testImage(800, 800)])
            ->call('create')
            ->assertHasFormErrors([Brand::IMAGE_LOGO]);

        expect(Brand::query()->count())->toBe(0);
    });

    it('uploads a category icon and both banner images', function (): void {
        $category = Category::factory()->create();

        livewire(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm([
                Category::IMAGE_ICON => testImage(256, 256),
                Category::IMAGE_BANNER_DESKTOP => testImage(1920, 480, 'jpg'),
                Category::IMAGE_BANNER_MOBILE => testImage(800, 600, 'jpg'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        expect($category->getIcon())->not->toBeNull()
            ->and($category->getBannerDesktop())->not->toBeNull()
            ->and($category->getBannerMobile())->not->toBeNull();
    });
});

describe('storefront', function (): void {
    it('shows brand logos on the brand list and the brand page', function (): void {
        $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);
        $logo = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(800, 400))->fresh();

        $this->get(route('brands.index'))->assertOk()->assertSee($logo->url(320, 'webp'), escape: false);
        $this->get('/brands/eucerin')->assertOk()->assertSee($logo->url(320, 'webp'), escape: false)->assertSee('fetchpriority="high"', escape: false);
    });

    it('shows the category banner and subcategory icons on the category page', function (): void {
        $category = Category::factory()->create([Category::COLUMN_SLUG => 'supplements']);
        $child = Category::factory()->childOf($category)->create();
        $banner = app(AttachImage::class)($category, Category::IMAGE_BANNER_DESKTOP, testImage(1920, 480, 'jpg'))->fresh();
        $icon = app(AttachImage::class)($child, Category::IMAGE_ICON, testImage(256, 256))->fresh();

        $this->get('/categories/supplements')
            ->assertOk()
            ->assertSee($banner->url(1920, 'jpg'), escape: false)
            ->assertSee($icon->url(96, 'webp'), escape: false);
    });

    it('still shows brands without a logo', function (): void {
        Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره']);

        $this->get(route('brands.index'))->assertOk()->assertSee('سینره')->assertDontSee('<picture>', escape: false);
    });
});
