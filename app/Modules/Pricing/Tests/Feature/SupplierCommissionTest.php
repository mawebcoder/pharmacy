<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Brands\Pages\EditBrand;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Pricing\Actions\ConfirmPriceImport;
use App\Modules\Pricing\Actions\SaveBrandProvider;
use App\Modules\Pricing\Actions\SaveProductProvider;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Actions\StartPriceImport;
use App\Modules\Pricing\Data\BrandProviderData;
use App\Modules\Pricing\Data\ProductProviderData;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\ProviderChoice;
use App\Modules\Pricing\Livewire\BrandProvidersTable;
use App\Modules\Pricing\Livewire\ProductProvidersTable;
use App\Modules\Pricing\Models\BrandProvider;
use App\Modules\Pricing\Models\ProductProvider;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Support\PriceSpreadsheet;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

/**
 * A SKU of a product (own commission optional) of a brand with a 10 % commission, no printed price.
 */
function commissionSku(?float $productCommission = null, ?Brand $brand = null): ProductVariant
{
    $product = Product::factory()->active()->for($brand ?? Brand::factory()->state([Brand::COLUMN_COMMISSION_PERCENT => 10]))->create([Product::COLUMN_COMMISSION_PERCENT => $productCommission]);

    return ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => 9_000_000]);
}

/**
 * @param  array<string, mixed>  $values
 */
function offerFrom(Provider $provider, ProductVariant $sku, int $cost, array $values = []): ProviderPrice
{
    $existing = ProviderPrice::query()->where(ProviderPrice::COLUMN_PROVIDER_ID, $provider->getId())->where(ProviderPrice::COLUMN_VARIANT_ID, $sku->getId())->first();

    return app(SaveProviderPrice::class)(ProviderPriceData::fromArray([
        ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(),
        ProviderPrice::COLUMN_VARIANT_ID => $sku->getId(),
        ProviderPrice::COLUMN_COST => $cost,
        ProviderPrice::COLUMN_IS_ENABLED => $existing?->isEnabled() ?? true,
        ProviderPrice::COLUMN_IS_PREFERRED => $existing?->isPreferred() ?? false,
        ProviderPrice::COLUMN_COMMISSION_PERCENT => $existing?->getCommissionPercent(),
        ...$values,
    ]), $existing);
}

function lastRecordOf(ProductVariant $sku): VariantPrice
{
    return VariantPrice::query()->where(VariantPrice::COLUMN_VARIANT_ID, $sku->getId())->latest('id')->firstOrFail();
}

describe('commission levels', function (): void {
    it('uses the first level that is set for the supplier', function (Closure $set, float $percent, CommissionSource $source): void {
        $sku = commissionSku(productCommission: 12);
        $sina = Provider::factory()->create();
        $set($sku, $sina);

        offerFrom($sina, $sku, 1_000_000);

        expect(lastRecordOf($sku))->getCommissionPercent()->toBe($percent)->getCommissionSource()->toBe($source);
    })->with([
        'SKU × supplier over everything' => [function (ProductVariant $sku, Provider $sina): void {
            offerFrom($sina, $sku, 1_000_000, [ProviderPrice::COLUMN_COMMISSION_PERCENT => 7]);
            $_ = app(SaveProductProvider::class)(new ProductProviderData($sku->getProductId(), $sina->getId(), 9, false));
        }, 7.0, CommissionSource::SkuProvider],
        'product × supplier over the product' => [function (ProductVariant $sku, Provider $sina): void {
            $_ = app(SaveProductProvider::class)(new ProductProviderData($sku->getProductId(), $sina->getId(), 9, false));
        }, 9.0, CommissionSource::ProductProvider],
        'product over brand × supplier' => [function (ProductVariant $sku, Provider $sina): void {
            $_ = app(SaveBrandProvider::class)(new BrandProviderData($sku->getProduct()->getBrandId(), $sina->getId(), 8));
        }, 12.0, CommissionSource::Product],
    ]);

    it('uses the brand × supplier commission over the brand for a product without its own', function (): void {
        $sku = commissionSku();
        $sina = Provider::factory()->create();
        $_ = app(SaveBrandProvider::class)(new BrandProviderData($sku->getProduct()->getBrandId(), $sina->getId(), 8));
        $alborz = Provider::factory()->create();

        offerFrom($sina, $sku, 1_000_000);
        // 1,000,000 × 1.08 = 1,080,000 rial (108,000 toman).
        expect(lastRecordOf($sku))->getCommissionSource()->toBe(CommissionSource::BrandProvider)->getPrice()->toBe(1_080_000);

        offerFrom($sina, $sku, 1_000_000, [ProviderPrice::COLUMN_IS_ENABLED => false]);
        offerFrom($alborz, $sku, 1_000_000);
        expect(lastRecordOf($sku))->getCommissionSource()->toBe(CommissionSource::Brand)->getPrice()->toBe(1_100_000);
    });
});

describe('which supplier sells', function (): void {
    it('sells from the lowest selling price, not the lowest cost', function (): void {
        $sku = commissionSku();
        [$sina, $alborz] = Provider::factory()->count(2)->create();
        offerFrom($sina, $sku, 8_500_000, [ProviderPrice::COLUMN_COMMISSION_PERCENT => 12]);
        offerFrom($alborz, $sku, 8_700_000, [ProviderPrice::COLUMN_COMMISSION_PERCENT => 8]);

        // Sina 952,000 vs Alborz 939,600 → 940,000 toman.
        expect(lastRecordOf($sku))
            ->getProviderId()->toBe($alborz->getId())
            ->getPrice()->toBe(9_400_000)
            ->getProviderChoice()->toBe(ProviderChoice::Cheapest);
    });

    it('sells from the preferred supplier while it can, then falls back and returns', function (): void {
        $sku = commissionSku();
        [$sina, $alborz] = Provider::factory()->count(2)->create();
        offerFrom($sina, $sku, 8_500_000, [ProviderPrice::COLUMN_COMMISSION_PERCENT => 12, ProviderPrice::COLUMN_IS_PREFERRED => true]);
        offerFrom($alborz, $sku, 8_700_000, [ProviderPrice::COLUMN_COMMISSION_PERCENT => 8]);
        expect(lastRecordOf($sku))->getProviderId()->toBe($sina->getId())->getPrice()->toBe(9_520_000)->getProviderChoice()->toBe(ProviderChoice::PreferredSku);

        offerFrom($sina, $sku, 8_500_000, [ProviderPrice::COLUMN_IS_AVAILABLE => false]);
        expect(lastRecordOf($sku))->getProviderId()->toBe($alborz->getId())->getProviderChoice()->toBe(ProviderChoice::Cheapest);

        offerFrom($sina, $sku, 8_500_000, [ProviderPrice::COLUMN_IS_AVAILABLE => true]);
        expect(lastRecordOf($sku))->getProviderId()->toBe($sina->getId());
    });

    it('keeps one preferred supplier per SKU', function (): void {
        $sku = commissionSku();
        [$sina, $alborz] = Provider::factory()->count(2)->create();
        $first = offerFrom($sina, $sku, 8_500_000, [ProviderPrice::COLUMN_IS_PREFERRED => true]);

        offerFrom($alborz, $sku, 8_700_000, [ProviderPrice::COLUMN_IS_PREFERRED => true]);

        expect($first->fresh()->isPreferred())->toBeFalse()
            ->and(lastRecordOf($sku)->getProviderId())->toBe($alborz->getId());
    });

    it('uses the product preferred supplier, unless the SKU has its own', function (): void {
        $sku = commissionSku();
        [$sina, $alborz, $hejrat] = Provider::factory()->count(3)->create();
        offerFrom($sina, $sku, 8_000_000);
        offerFrom($alborz, $sku, 8_500_000);
        offerFrom($hejrat, $sku, 9_000_000);

        $_ = app(SaveProductProvider::class)(new ProductProviderData($sku->getProductId(), $alborz->getId(), null, true));
        expect(lastRecordOf($sku))->getProviderId()->toBe($alborz->getId())->getProviderChoice()->toBe(ProviderChoice::PreferredProduct);

        offerFrom($hejrat, $sku, 9_000_000, [ProviderPrice::COLUMN_IS_PREFERRED => true]);
        expect(lastRecordOf($sku))->getProviderId()->toBe($hejrat->getId())->getProviderChoice()->toBe(ProviderChoice::PreferredSku);
    });

    it('reprices only the brand SKUs bought from the supplier whose brand commission changes', function (): void {
        $brand = Brand::factory()->create([Brand::COLUMN_COMMISSION_PERCENT => 10]);
        $fromSina = commissionSku(brand: $brand);
        $fromAlborz = commissionSku(brand: $brand);
        $otherBrand = commissionSku();
        [$sina, $alborz] = Provider::factory()->count(2)->create();
        offerFrom($sina, $fromSina, 1_000_000);
        offerFrom($alborz, $fromAlborz, 1_000_000);
        offerFrom($sina, $otherBrand, 1_000_000);

        $_ = app(SaveBrandProvider::class)(new BrandProviderData($brand->getId(), $sina->getId(), 20));

        expect($fromSina->fresh()->getPrice())->toBe(1_200_000)
            ->and($fromAlborz->fresh()->getPrice())->toBe(1_100_000)
            ->and($otherBrand->fresh()->getPrice())->toBe(1_100_000);
    });
});

describe('Excel', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
    });

    it('sets and clears the SKU × supplier commission and the preferred supplier', function (): void {
        $sku = commissionSku();
        $sku->setSku('EUC-0001-01')->save();
        [$sina, $alborz] = Provider::factory()->count(2)->create();
        $sinaRow = offerFrom($sina, $sku, 1_000_000, [ProviderPrice::COLUMN_COMMISSION_PERCENT => 5]);
        $path = 'imports/prices/s15b.xlsx';
        Storage::disk('local')->put($path, '');
        PriceSpreadsheet::writeTemplate(Storage::disk('local')->path($path), [
            [$sina->getId(), '', 'EUC-0001-01', '', '', 100_000, '', '', '', '-', ''],
            [$alborz->getId(), '', 'EUC-0001-01', '', '', 100_000, '', '', '', '۲۰', 'بله'],
        ]);
        $import = app(StartPriceImport::class)($path, 'prices.xlsx')->fresh();

        app(ConfirmPriceImport::class)($import);

        expect($sinaRow->fresh()->getCommissionPercent())->toBeNull()
            ->and(ProviderPrice::query()->where(ProviderPrice::COLUMN_PROVIDER_ID, $alborz->getId())->sole())->getCommissionPercent()->toBe(20.0)->isPreferred()->toBeTrue()
            ->and(lastRecordOf($sku))->getProviderId()->toBe($alborz->getId())->getPrice()->toBe(1_200_000);
    });
});

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
    });

    it('sets a supplier commission and preferred supplier on a product', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        $sku = commissionSku();
        $sina = Provider::factory()->create();

        livewire(ProductProvidersTable::class, ['product' => $sku->getProductId()])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                ProductProvider::COLUMN_PROVIDER_ID => $sina->getId(),
                ProductProvider::COLUMN_COMMISSION_PERCENT => 9,
                ProductProvider::COLUMN_IS_PREFERRED => true,
            ])
            ->assertHasNoFormErrors();

        expect(ProductProvider::query()->sole())->getCommissionPercent()->toBe(9.0)->isPreferred()->toBeTrue();
    });

    it('sets a supplier commission on a brand', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        $brand = Brand::factory()->create();
        $sina = Provider::factory()->create();

        livewire(BrandProvidersTable::class, ['brand' => $brand->getId()])
            ->callAction(TestAction::make(CreateAction::class)->table(), [BrandProvider::COLUMN_PROVIDER_ID => $sina->getId(), BrandProvider::COLUMN_COMMISSION_PERCENT => 8])
            ->assertHasNoFormErrors();

        expect(BrandProvider::query()->sole()->getCommissionPercent())->toBe(8.0);
    });

    it('hides the supplier commissions from a content editor', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
        $brand = Brand::factory()->create();

        livewire(EditBrand::class, ['record' => $brand->getRouteKey()])->assertDontSeeLivewire(BrandProvidersTable::class);
    });
});
