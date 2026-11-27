<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Filament\Resources\Products\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\ProductVisibility;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

it('finds nothing wrong with an active product that has a brand, a subcategory and an active SKU', function (): void {
    expect(ProductVisibility::problems(Product::factory()->active()->withVariants()->create()))->toBe([]);
});

it('names what keeps a product off the storefront', function (Closure $make, string $reason): void {
    expect(ProductVisibility::problems($make()))->toContain($reason);
})->with([
    'draft' => [fn (): Product => Product::factory()->withVariants()->create([Product::COLUMN_STATUS => ProductStatus::Draft]), 'وضعیت «پیش‌نویس» است؛ باید «فعال» باشد.'],
    'prescription drug' => [fn (): Product => Product::factory()->active()->rx()->withVariants()->create(), 'داروی نسخه‌ای است: در فهرست‌ها و جستجو نیست و صفحه‌اش «فروش آنلاین به‌زودی» نشان می‌دهد.'],
    'no active SKU' => [function (): Product {
        $product = Product::factory()->active()->create();
        ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_IS_ACTIVE => false]);

        return $product;
    }, 'هیچ تنوع فعالی ندارد.'],
]);

describe('the product form', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    });

    it('explains what a product needs to be shown when creating one', function (): void {
        livewire(CreateProduct::class)->assertSee(['نمایش در سایت', 'دست‌کم یک تنوع فعال']);
    });

    it('says whether the product being edited is shown, and why not', function (): void {
        $shown = Product::factory()->active()->withVariants()->create();
        $hidden = Product::factory()->withVariants()->create([Product::COLUMN_STATUS => ProductStatus::Hidden]);

        livewire(EditProduct::class, ['record' => $shown->getRouteKey()])->assertSee('این محصول در سایت نمایش داده می‌شود');
        livewire(EditProduct::class, ['record' => $hidden->getRouteKey()])
            ->assertSee(['این محصول در فهرست‌ها و جستجوی سایت نمایش داده نمی‌شود', 'وضعیت «پنهان» است؛ باید «فعال» باشد.']);
    });
});
