<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Pages\VariantsWithoutSuppliers;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Pricing\Filament\Pages\SkuSuppliers;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\CreateProvider;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\ListProviders;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\ViewProvider;
use App\Modules\Pricing\Filament\Resources\Providers\RelationManagers\ProviderPricesRelationManager;
use App\Modules\Pricing\Livewire\PriceHistoryTable;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

function signInAs(AdminRole $role): void
{
    test()->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole($role)->create(), 'admin');
}

function skuOfBrandWithCommission(): ProductVariant
{
    $product = Product::factory()->active()->for(Brand::factory()->state([Brand::COLUMN_COMMISSION_PERCENT => 10]))->create();

    return ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => 9_000_000]);
}

describe('as a super admin', function (): void {
    beforeEach(function (): void {
        signInAs(AdminRole::SuperAdmin);
    });

    it('creates a supplier', function (): void {
        livewire(CreateProvider::class)
            ->fillForm([Provider::COLUMN_NAME => 'پخش سینا', Provider::COLUMN_PHONE => '۰۲۱۸۸۰۰۰۰۰۰'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Provider::query()->sole())->getName()->toBe('پخش سینا')->getPhone()->toBe('02188000000')->isActive()->toBeTrue();
    });

    it('adds a supplier to a SKU, which then sells from it', function (): void {
        $sku = skuOfBrandWithCommission();
        $provider = Provider::factory()->create(['name' => 'پخش البرز']);

        livewire(SkuSuppliers::class, ['variant' => $sku->getId()])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(),
                ProviderPrice::COLUMN_COST => '850,000',
                ProviderPrice::COLUMN_IS_AVAILABLE => true,
                ProviderPrice::COLUMN_IS_ENABLED => true,
            ])
            ->assertHasNoFormErrors()
            ->assertSee('فعال: فروش از این تأمین‌کننده')
            ->assertSee('۹۳۵٬۰۰۰ تومان');

        expect($sku->fresh()->getPrice())->toBe(9_350_000);
    });

    it('stops selling from a supplier with its switch', function (): void {
        $sku = skuOfBrandWithCommission();
        $price = ProviderPrice::factory()->forVariant($sku->getId())->create([ProviderPrice::COLUMN_COST => 8_500_000]);

        livewire(SkuSuppliers::class, ['variant' => $sku->getId()])
            ->call('updateTableColumnState', ProviderPrice::COLUMN_IS_ENABLED, (string) $price->getId(), false);

        expect($price->fresh()->isEnabled())->toBeFalse();
    });

    it('lists the SKU price records in the history table', function (): void {
        $sku = skuOfBrandWithCommission();
        $record = VariantPrice::factory()->create([VariantPrice::COLUMN_VARIANT_ID => $sku->getId(), VariantPrice::COLUMN_PRICE => 9_350_000]);

        livewire(PriceHistoryTable::class, ['variant' => $sku->getId()])->assertCanSeeTableRecords([$record]);
    });

    it('shows the active supplier in the product SKU table', function (): void {
        $sku = skuOfBrandWithCommission();
        $provider = Provider::factory()->create(['name' => 'پخش هجرت']);
        ProviderPrice::factory()->forVariant($sku->getId())->create([ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId()]);

        livewire(VariantsRelationManager::class, ['ownerRecord' => $sku->getProduct(), 'pageClass' => EditProduct::class])
            ->assertTableColumnStateSet('active_supplier', 'پخش هجرت', $sku);
    });

    it('lists a supplier SKUs with their price information on its view page', function (): void {
        $provider = Provider::factory()->create();
        $sku = skuOfBrandWithCommission();
        $price = ProviderPrice::factory()->forVariant($sku->getId())->create([ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(), ProviderPrice::COLUMN_COST => 8_500_000]);
        ProviderPrice::factory()->create();

        livewire(ProviderPricesRelationManager::class, ['ownerRecord' => $provider, 'pageClass' => ViewProvider::class])
            ->assertCanSeeTableRecords([$price])
            ->assertCountTableRecords(1)
            ->assertTableColumnStateSet('selling_with_commission', '۹۳۵٬۰۰۰ تومان', $price);
    });

    it('adds a SKU to a supplier once', function (): void {
        $provider = Provider::factory()->create();
        $sku = skuOfBrandWithCommission();
        $fields = [ProviderPrice::COLUMN_VARIANT_ID => $sku->getId(), ProviderPrice::COLUMN_COST => '850,000'];

        livewire(ProviderPricesRelationManager::class, ['ownerRecord' => $provider, 'pageClass' => ViewProvider::class])
            ->callAction(TestAction::make(CreateAction::class)->table(), $fields)
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make(CreateAction::class)->table(), $fields)
            ->assertHasFormErrors([ProviderPrice::COLUMN_VARIANT_ID]);

        expect($sku->fresh()->getPrice())->toBe(9_350_000)
            ->and(ProviderPrice::query()->count())->toBe(1);
    });

    it('lists the SKUs that have no supplier', function (): void {
        $without = skuOfBrandWithCommission();
        $with = skuOfBrandWithCommission();
        ProviderPrice::factory()->forVariant($with->getId())->create();

        livewire(VariantsWithoutSuppliers::class)->assertCanSeeTableRecords([$without])->assertCanNotSeeTableRecords([$with]);
    });
});

it('keeps supplier costs from a content editor', function (string $page): void {
    signInAs(AdminRole::ContentEditor);
    $sku = skuOfBrandWithCommission();

    livewire($page, $page === SkuSuppliers::class ? ['variant' => $sku->getId()] : [])->assertForbidden();
})->with([
    'suppliers' => ListProviders::class,
    'SKU suppliers page' => SkuSuppliers::class,
    'SKUs without supplier' => VariantsWithoutSuppliers::class,
]);

it('hides the active supplier column from a content editor', function (): void {
    signInAs(AdminRole::ContentEditor);
    $sku = skuOfBrandWithCommission();

    livewire(VariantsRelationManager::class, ['ownerRecord' => $sku->getProduct(), 'pageClass' => EditProduct::class])
        ->assertTableColumnHidden('active_supplier');
});
