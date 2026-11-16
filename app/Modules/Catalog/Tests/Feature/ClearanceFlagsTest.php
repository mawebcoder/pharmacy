<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\SaveProductVariant;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/*
| «زودمصرف» is a flag the admin sets by hand on a SKU (owner, 2026-11-16): SKUs no longer have an expiry date, so
| nothing sets it automatically. The admin product list filters on it, to find the products to put on haraj.
*/

it('saves «زودمصرف» as the admin sets it', function (bool $near): void {
    $product = Product::factory()->create();

    $variant = app(SaveProductVariant::class)($product, ProductVariantData::fromArray([
        ProductVariant::COLUMN_TITLE_FA => '۵۰ گرمی',
        ProductVariant::COLUMN_IS_NEAR_EXPIRY => $near,
    ]));

    expect($variant->isNearExpiry())->toBe($near);
})->with([true, false]);

it('filters the admin product list to products with a «زودمصرف» SKU', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    $near = Product::factory()->create();
    ProductVariant::factory()->for($near)->nearExpiry()->create();
    $sound = Product::factory()->create();
    ProductVariant::factory()->for($sound)->create();

    livewire(ListProducts::class)
        ->filterTable('near_expiry')
        ->assertCanSeeTableRecords([$near])
        ->assertCanNotSeeTableRecords([$sound]);
});
