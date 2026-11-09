<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Filament\Resources\Products\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Filament\Resources\Products\Schemas\ProductForm;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Storage::fake('local');
    Storage::fake('media');
    $this->seed(RolesSeeder::class);
});

describe('as a content editor', function (): void {
    beforeEach(function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    });

    it('creates a draft product with its first SKU and continues on its edit page', function (): void {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                Product::COLUMN_NAME_FA => 'کرم مرطوب‌کننده',
                Product::COLUMN_NAME_EN => 'Moisturising Cream',
                Product::COLUMN_BRAND_ID => $brand->getId(),
                Product::COLUMN_PRIMARY_CATEGORY_ID => $category->getId(),
                Product::COLUMN_PRODUCT_TYPE => 'cosmetic',
                ProductForm::FIRST_VARIANT.'.'.ProductVariant::COLUMN_TITLE_FA => '۵۰ میلی‌لیتر',
                ProductForm::FIRST_VARIANT.'.'.ProductVariant::COLUMN_OFFICIAL_PRICE => '485,000',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(EditProduct::getUrl(['record' => 'moisturising-cream']));

        $product = Product::query()->sole();
        expect($product->getStatus())->toBe(ProductStatus::Draft)
            ->and($product->variants()->sole())
            ->getTitleFa()->toBe('۵۰ میلی‌لیتر')
            ->getOfficialPrice()->toBe(4_850_000)
            // No supplier yet: sold at its printed price (roadmap D3.2).
            ->getPrice()->toBe(4_850_000);
    });

    it('asks for the first SKU on create', function (): void {
        livewire(CreateProduct::class)
            ->fillForm([
                Product::COLUMN_NAME_FA => 'کرم مرطوب‌کننده',
                Product::COLUMN_BRAND_ID => Brand::factory()->create()->getId(),
                Product::COLUMN_PRIMARY_CATEGORY_ID => Category::factory()->create()->getId(),
            ])
            ->call('create')
            ->assertHasFormErrors([
                ProductForm::FIRST_VARIANT.'.'.ProductVariant::COLUMN_TITLE_FA => 'required',
            ]);

        expect(Product::query()->count())->toBe(0);
    });

    it('adds two variants with box prices typed in toman, sold at those prices until they have suppliers', function (): void {
        $product = Product::factory()->create();

        foreach (['۵۰ میلی‌لیتر' => '485,000', '۲۰۰ میلی‌لیتر' => '1,250,000'] as $title => $price) {
            livewire(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
                ->callAction(TestAction::make(CreateAction::class)->table(), [
                    ProductVariant::COLUMN_TITLE_FA => $title,
                    ProductVariant::COLUMN_OFFICIAL_PRICE => $price,
                    ProductVariant::COLUMN_IS_ACTIVE => true,
                ])
                ->assertHasNoFormErrors();
        }

        expect($product->variants()->pluck(ProductVariant::COLUMN_OFFICIAL_PRICE)->all())->toBe([4_850_000, 12_500_000])
            ->and($product->variants()->pluck(ProductVariant::COLUMN_PRICE)->all())->toBe([4_850_000, 12_500_000]);
    });

    it('uploads the first photo and activates the product in one save', function (): void {
        $product = Product::factory()->withVariants()->create();

        livewire(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([Product::COLUMN_STATUS => 'active', Product::IMAGE_GALLERY => [testImage(1200, 1200, 'jpg')]])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->fresh())
            ->getStatus()->toBe(ProductStatus::Active)
            ->getMedia(Product::IMAGE_GALLERY)->toHaveCount(1);
    });

    it('shows what is missing when a product cannot be activated', function (): void {
        $product = Product::factory()->create();

        livewire(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([Product::COLUMN_STATUS => 'active'])
            ->call('save')
            ->assertNotified();

        expect($product->fresh()->getStatus())->toBe(ProductStatus::Draft);
    });

    it('cannot delete products', function (): void {
        $product = Product::factory()->create();

        livewire(EditProduct::class, ['record' => $product->getRouteKey()])->assertActionHidden(DeleteAction::class);
    });
});

it('lets an operator look up products without changing them', function (): void {
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();
    $product = Product::factory()->withVariants()->create();
    $this->actingAs($operator, 'admin');

    $this->get('/admin/products')->assertOk();
    $this->get('/admin/products/create')->assertForbidden();

    livewire(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->assertActionHidden(TestAction::make(CreateAction::class)->table());
});

it('finds products by Persian or English name', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    $cream = Product::factory()->create([Product::COLUMN_NAME_FA => 'کرم اوره', Product::COLUMN_NAME_EN => 'Urea Cream']);
    $syrup = Product::factory()->create([Product::COLUMN_NAME_FA => 'شربت سرفه', Product::COLUMN_NAME_EN => 'Cough Syrup']);

    livewire(ListProducts::class)->searchTable('Urea')->assertCanSeeTableRecords([$cream])->assertCanNotSeeTableRecords([$syrup]);
    livewire(ListProducts::class)->searchTable('شربت')->assertCanSeeTableRecords([$syrup])->assertCanNotSeeTableRecords([$cream]);
});
