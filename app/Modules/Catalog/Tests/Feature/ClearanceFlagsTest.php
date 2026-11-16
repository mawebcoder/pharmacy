<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/*
| «زودمصرف» is a flag the admin sets by hand on the product (owner, 2026-11-16): nothing sets it automatically. The
| admin product list filters on it, to find the products to put on haraj.
*/

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
});

it('saves «زودمصرف» on the product as the admin sets it', function (): void {
    $product = Product::factory()->withVariants()->create();

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm([Product::COLUMN_IS_NEAR_EXPIRY => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->isNearExpiry())->toBeTrue();
});

it('filters the admin product list to «زودمصرف» products', function (): void {
    $near = Product::factory()->nearExpiry()->create();
    ProductVariant::factory()->for($near)->create();
    $sound = Product::factory()->create();
    ProductVariant::factory()->for($sound)->create();

    livewire(ListProducts::class)
        ->filterTable('near_expiry')
        ->assertCanSeeTableRecords([$near])
        ->assertCanNotSeeTableRecords([$sound]);
});
