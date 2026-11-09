<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\SaveProductVariant;
use App\Modules\Catalog\Actions\UpdateBrand;
use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductDocument;
use App\Modules\Pricing\Actions\DeleteProviderPrice;
use App\Modules\Pricing\Actions\SaveProvider;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\ProviderPriceEvent;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\ProviderPriceChange;
use App\Modules\Pricing\Models\VariantPrice;
use Illuminate\Support\Facades\Queue;

/**
 * A SKU of an active product whose brand has the given commission, priced by hand before suppliers (legacy).
 */
function pricedSku(?float $brandCommission = 10, ?float $productCommission = null, ?int $officialPrice = null): ProductVariant
{
    $product = Product::factory()
        ->active()
        ->for(Brand::factory()->state([Brand::COLUMN_COMMISSION_PERCENT => $brandCommission]))
        ->create([Product::COLUMN_COMMISSION_PERCENT => $productCommission]);

    return ProductVariant::factory()->for($product)->create([
        ProductVariant::COLUMN_OFFICIAL_PRICE => $officialPrice,
        ProductVariant::COLUMN_PRICE => 9_000_000,
        ProductVariant::COLUMN_STOCK_STATUS => StockStatus::Available,
    ]);
}

/**
 * Adds or changes a supplier's price of the SKU (cost in Rial) through the action, as the admin page does.
 *
 * @param  array<string, mixed>  $values
 */
function supplierPrice(ProductVariant $sku, int $cost, array $values = [], ?Provider $provider = null): ProviderPrice
{
    $provider ??= Provider::factory()->create();
    $existing = ProviderPrice::query()->where(ProviderPrice::COLUMN_PROVIDER_ID, $provider->getId())->where(ProviderPrice::COLUMN_VARIANT_ID, $sku->getId())->first();

    return app(SaveProviderPrice::class)(ProviderPriceData::fromArray([
        ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(),
        ProviderPrice::COLUMN_VARIANT_ID => $sku->getId(),
        ProviderPrice::COLUMN_COST => $cost,
        ...$values,
    ]), $existing);
}

function latestRecord(ProductVariant $sku): VariantPrice
{
    return VariantPrice::query()->where(VariantPrice::COLUMN_VARIANT_ID, $sku->getId())->latest(VariantPrice::COLUMN_ID)->firstOrFail();
}

describe('active supplier', function (): void {
    it('prices the SKU from the cheapest supplier it can sell from', function (): void {
        $sku = pricedSku();
        supplierPrice($sku, 9_000_000);
        $cheapest = supplierPrice($sku, 8_500_000);

        // 850,000 × 1.10 = 935,000 toman.
        expect($sku->fresh())->getPrice()->toBe(9_350_000)->getStockStatus()->toBe(StockStatus::Available)
            ->and(latestRecord($sku))
            ->getProviderId()->toBe($cheapest->getProviderId())
            ->getProviderCost()->toBe(8_500_000)
            ->getCommissionPercent()->toBe(10.0)
            ->getCommissionSource()->toBe(CommissionSource::Brand)
            ->getReason()->toBe(PriceChangeReason::ProviderPriceChanged)
            ->and($sku->fresh()->getCurrentPriceId())->toBe(latestRecord($sku)->getId());
    });

    it('reprices the SKU whenever the active supplier changes its price', function (): void {
        $sku = pricedSku();
        $price = supplierPrice($sku, 8_500_000);

        supplierPrice($sku, 8_000_000, provider: $price->getProvider());

        // 800,000 × 1.10 = 880,000 toman.
        expect($sku->fresh()->getPrice())->toBe(8_800_000)
            ->and(latestRecord($sku)->getProviderCost())->toBe(8_000_000);
    });

    it('skips a supplier that is stopped, out of stock or switched off', function (Closure $skip): void {
        $sku = pricedSku();
        supplierPrice($sku, 9_000_000);
        $skip($sku);

        expect($sku->fresh()->getPrice())->toBe(9_900_000);
    })->with([
        'stopped by the admin' => [fn (ProductVariant $sku): ProviderPrice => supplierPrice($sku, 8_500_000, [ProviderPrice::COLUMN_IS_ENABLED => false])],
        'out of stock at the supplier' => [fn (ProductVariant $sku): ProviderPrice => supplierPrice($sku, 8_500_000, [ProviderPrice::COLUMN_IS_AVAILABLE => false])],
        'supplier switched off' => [fn (ProductVariant $sku): ProviderPrice => supplierPrice($sku, 8_500_000, provider: Provider::factory()->inactive()->create())],
    ]);

    it('hands over to the next cheapest supplier when the active one is stopped', function (): void {
        $sku = pricedSku();
        $sina = Provider::factory()->create();
        supplierPrice($sku, 8_500_000, provider: $sina);
        supplierPrice($sku, 8_700_000);

        supplierPrice($sku, 8_500_000, [ProviderPrice::COLUMN_IS_ENABLED => false], $sina);

        // 870,000 × 1.10 = 957,000 toman.
        expect($sku->fresh()->getPrice())->toBe(9_570_000)
            ->and(latestRecord($sku))->getProviderCost()->toBe(8_700_000)->getReason()->toBe(PriceChangeReason::ProviderPriceChanged);
    });

    it('reprices every SKU of a supplier that is switched off', function (): void {
        $provider = Provider::factory()->create();
        $sku = pricedSku();
        supplierPrice($sku, 8_500_000, provider: $provider);
        supplierPrice($sku, 8_700_000);

        $_ = app(SaveProvider::class)(ProviderData::fromArray([Provider::COLUMN_NAME => $provider->getName(), Provider::COLUMN_IS_ACTIVE => false]), $provider);

        expect($sku->fresh()->getPrice())->toBe(9_570_000);
    });

    it('marks the SKU out of stock and keeps its price when no supplier can sell it and it has no printed price', function (): void {
        $sku = pricedSku();
        $only = supplierPrice($sku, 8_500_000);

        supplierPrice($sku, 8_500_000, [ProviderPrice::COLUMN_IS_AVAILABLE => false], $only->getProvider());

        expect($sku->fresh())->getStockStatus()->toBe(StockStatus::OutOfStock)->getPrice()->toBe(9_350_000);
    });

    it('sells at the printed price when no supplier can sell it', function (): void {
        $sku = pricedSku(officialPrice: 9_900_000);
        $only = supplierPrice($sku, 8_500_000);

        supplierPrice($sku, 8_500_000, [ProviderPrice::COLUMN_IS_ENABLED => false], $only->getProvider());

        expect($sku->fresh())->getPrice()->toBe(9_900_000)->getStockStatus()->toBe(StockStatus::Available)
            ->and(latestRecord($sku)->hasFlag(VariantPrice::FLAG_SOLD_AT_PRINTED))->toBeTrue();
    });

    it('sells at the printed price when neither the product nor its brand has a commission', function (): void {
        $sku = pricedSku(brandCommission: null, officialPrice: 9_900_000);

        supplierPrice($sku, 8_500_000);

        expect($sku->fresh()->getPrice())->toBe(9_900_000)
            ->and(latestRecord($sku))->getProviderId()->toBeNull()->getCommissionPercent()->toBeNull();
    });

    it('marks the SKU out of stock when its last supplier is removed and it has no printed price', function (): void {
        $sku = pricedSku();
        $only = supplierPrice($sku, 8_500_000);

        app(DeleteProviderPrice::class)($only);

        expect($sku->fresh()->getStockStatus())->toBe(StockStatus::OutOfStock)
            ->and(ProviderPriceChange::query()->latest('id')->first()?->getEvent())->toBe(ProviderPriceEvent::Deleted);
    });
});

describe('selling price', function (): void {
    it('rounds up to a whole 1,000 toman', function (): void {
        $sku = pricedSku();

        supplierPrice($sku, 8_533_300);

        // 853,330 × 1.10 = 938,663 → 939,000 toman.
        expect($sku->fresh()->getPrice())->toBe(9_390_000);
    });

    it('uses the product commission over the brand one', function (): void {
        $sku = pricedSku(brandCommission: 10, productCommission: 20);

        supplierPrice($sku, 8_500_000);

        expect($sku->fresh()->getPrice())->toBe(10_200_000)
            ->and(latestRecord($sku)->getCommissionSource())->toBe(CommissionSource::Product);
    });

    it('never sells above the printed price and flags the lower margin', function (): void {
        $sku = pricedSku(officialPrice: 9_000_000);

        supplierPrice($sku, 8_500_000);

        expect($sku->fresh()->getPrice())->toBe(9_000_000)
            ->and(latestRecord($sku)->hasFlag(VariantPrice::FLAG_CAPPED_BY_PRINTED))->toBeTrue();
    });

    it('removes a haraj that is no longer below the selling price', function (): void {
        $sku = pricedSku();
        $sku->setSalePrice(9_000_000)->save();

        supplierPrice($sku, 7_000_000);

        // 700,000 × 1.10 = 770,000 toman, below the 900,000 haraj.
        expect($sku->fresh())->getPrice()->toBe(7_700_000)->getSalePrice()->toBeNull()
            ->and(latestRecord($sku)->hasFlag(VariantPrice::FLAG_HARAJ_REMOVED))->toBeTrue();
    });

    it('flags a change of more than 15 %', function (): void {
        $sku = pricedSku();

        supplierPrice($sku, 5_000_000);

        expect(latestRecord($sku)->hasFlag(VariantPrice::FLAG_CHANGE_OVER_15_PERCENT))->toBeTrue();
    });

    it('reprices the products that follow a brand when its commission changes', function (): void {
        $follows = pricedSku(brandCommission: 10);
        $brand = $follows->getProduct()->getBrand();
        $own = ProductVariant::factory()->for(Product::factory()->active()->for($brand)->create([Product::COLUMN_COMMISSION_PERCENT => 20]))->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null]);
        supplierPrice($follows, 8_500_000);
        supplierPrice($own, 8_500_000);

        $_ = app(UpdateBrand::class)($brand, BrandData::fromArray([Brand::COLUMN_NAME_FA => $brand->getNameFa(), Brand::COLUMN_COMMISSION_PERCENT => 30]));

        expect($follows->fresh()->getPrice())->toBe(11_050_000)
            ->and($own->fresh()->getPrice())->toBe(10_200_000)
            ->and(latestRecord($follows)->getReason())->toBe(PriceChangeReason::CommissionChanged);
    });
});

describe('price records', function (): void {
    it('writes no record when the selling price inputs do not change', function (): void {
        $sku = pricedSku();
        supplierPrice($sku, 8_500_000);
        $records = VariantPrice::query()->count();

        // A dearer supplier changes nothing on the website, but its cost history is kept.
        supplierPrice($sku, 9_500_000);

        expect(VariantPrice::query()->count())->toBe($records)
            ->and(ProviderPriceChange::query()->count())->toBe(2);
    });

    it('sells a SKU without suppliers at its new printed price', function (): void {
        $sku = pricedSku();

        $_ = app(SaveProductVariant::class)($sku->getProduct(), ProductVariantData::fromArray([
            ProductVariant::COLUMN_TITLE_FA => $sku->getTitleFa(),
            ProductVariant::COLUMN_OFFICIAL_PRICE => 12_000_000,
            ProductVariant::COLUMN_STOCK_STATUS => 'available',
        ]), $sku);

        expect($sku->fresh())->getPrice()->toBe(12_000_000)->getStockStatus()->toBe(StockStatus::Available)
            ->and(latestRecord($sku))
            ->getOfficialPrice()->toBe(12_000_000)
            ->getProviderId()->toBeNull()
            ->and(latestRecord($sku)->hasFlag(VariantPrice::FLAG_SOLD_AT_PRINTED))->toBeTrue();
    });

    it('recalculates in the background on the pricing queue', function (): void {
        Queue::fake();
        $sku = pricedSku();

        supplierPrice($sku, 8_500_000);

        Queue::assertPushedOn('pricing', RecalculateVariantPrices::class);
        expect($sku->fresh()->getPrice())->toBe(9_000_000);
    });
});

it('keeps supplier costs out of the search document', function (): void {
    $sku = pricedSku();
    supplierPrice($sku, 8_513_300);

    expect(json_encode(ProductDocument::for($sku->getProduct()->fresh())))->not->toContain('8513300');
});
