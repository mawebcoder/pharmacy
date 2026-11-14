<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Queries;

use App\Modules\Catalog\Data\VariantPricingInput;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Actions\CalculateVariantPrice;
use App\Modules\Pricing\Data\ChosenSupplier;
use App\Modules\Pricing\Data\SupplierOffer;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\ProviderChoice;
use App\Modules\Pricing\Models\BrandProvider;
use App\Modules\Pricing\Models\ProductProvider;
use App\Modules\Pricing\Models\ProviderPrice;

/**
 * Which supplier the website sells each SKU from (roadmap D3.2, S15b).
 *
 * Commission, per supplier, the first level that is set: SKU × supplier (the supplier's row), product × supplier,
 * product, brand × supplier, brand. Supplier: the SKU's preferred one while it can sell, else the product's preferred
 * one while it can sell, else the one with the lowest selling price (each cost with its own commission; ties go to
 * the lower cost). A supplier can sell when the admin sells from it, it has the SKU, its supplier is active and a
 * commission applies.
 */
final readonly class ActiveSuppliers
{
    public function __construct(private VariantPricingInputs $inputs) {}

    /**
     * @param  array<int, VariantPricingInput>  $inputs  keyed by variant id
     * @return array<int, ChosenSupplier|null> keyed by variant id
     */
    public function for(array $inputs): array
    {
        $offers = $this->offers($inputs);
        $preferredByProduct = $this->preferredByProduct($inputs);
        $chosen = [];

        foreach ($inputs as $variantId => $input) {
            $usable = array_values(array_filter($offers[$variantId] ?? [], fn (SupplierOffer $offer): bool => $offer->usable));
            $chosen[$variantId] = $this->choose($usable, $preferredByProduct[$input->productId] ?? null);
        }

        return $chosen;
    }

    /**
     * Like for(), loading the Catalog inputs itself.
     *
     * @param  list<int>  $variantIds
     * @return array<int, ChosenSupplier|null>
     */
    public function forVariants(array $variantIds): array
    {
        return $this->for($this->inputs->for($variantIds));
    }

    /**
     * The supplier name each SKU sells from, for admin tables (SKUs without one are left out).
     *
     * @param  list<int>  $variantIds
     * @return array<int, string>
     */
    public function names(array $variantIds): array
    {
        $names = [];

        foreach ($this->forVariants($variantIds) as $variantId => $chosen) {
            $name = $chosen?->offer->price->getProvider()?->getName();

            if ($name !== null) {
                $names[$variantId] = $name;
            }
        }

        return $names;
    }

    /**
     * Every supplier row of these SKUs with its commission, selling price and whether it can sell, cheapest first.
     *
     * @param  array<int, VariantPricingInput>  $inputs
     * @return array<int, list<SupplierOffer>> keyed by variant id
     */
    public function offers(array $inputs): array
    {
        if ($inputs === []) {
            return [];
        }

        $productIds = array_values(array_unique(array_map(fn (VariantPricingInput $input): int => $input->productId, $inputs)));
        $brandIds = array_values(array_unique(array_filter(array_map(fn (VariantPricingInput $input): ?int => $input->brandId, $inputs))));
        $productCommissions = $this->productCommissions($productIds);
        $brandCommissions = $this->brandCommissions($brandIds);
        $offers = [];

        $rows = ProviderPrice::query()
            ->with('provider')
            ->whereIn(ProviderPrice::COLUMN_VARIANT_ID, array_keys($inputs))
            ->orderBy(ProviderPrice::COLUMN_COST)
            ->orderBy(ProviderPrice::COLUMN_ID)
            ->get();

        foreach ($rows as $row) {
            $input = $inputs[$row->getVariantId()];
            [$commission, $source] = self::commission(
                $row,
                $productCommissions[$input->productId][$row->getProviderId()] ?? null,
                $input->productCommission,
                $input->brandId === null ? null : ($brandCommissions[$input->brandId][$row->getProviderId()] ?? null),
                $input->brandCommission,
            );
            $selling = $commission === null ? null : CalculateVariantPrice::sellingPrice($row->getCost(), $commission);
            $capped = $selling !== null && $input->officialPrice !== null && $input->officialPrice > 0 && $selling > $input->officialPrice;

            $offers[$row->getVariantId()][] = new SupplierOffer(
                price: $row,
                commissionPercent: $commission,
                commissionSource: $source,
                sellingPrice: $capped ? $input->officialPrice : $selling,
                cappedByPrinted: $capped,
                usable: $selling !== null && $row->isEnabled() && $row->isAvailable() && ($row->getProvider()?->isActive() ?? false),
            );
        }

        return $offers;
    }

    /**
     * The commission of one supplier row and its level, or [null, null] when no level is set.
     *
     * @return array{0: float|null, 1: CommissionSource|null}
     */
    public static function commission(ProviderPrice $row, ?float $productProvider, ?float $product, ?float $brandProvider, ?float $brand): array
    {
        return match (true) {
            $row->getCommissionPercent() !== null => [$row->getCommissionPercent(), CommissionSource::SkuProvider],
            $productProvider !== null => [$productProvider, CommissionSource::ProductProvider],
            $product !== null => [$product, CommissionSource::Product],
            $brandProvider !== null => [$brandProvider, CommissionSource::BrandProvider],
            $brand !== null => [$brand, CommissionSource::Brand],
            default => [null, null],
        };
    }

    /**
     * @param  list<SupplierOffer>  $usable
     */
    private function choose(array $usable, ?int $productPreferredProviderId): ?ChosenSupplier
    {
        foreach ($usable as $offer) {
            if ($offer->price->isPreferred()) {
                return new ChosenSupplier($offer, ProviderChoice::PreferredSku);
            }
        }

        foreach ($usable as $offer) {
            if ($productPreferredProviderId !== null && $offer->price->getProviderId() === $productPreferredProviderId) {
                return new ChosenSupplier($offer, ProviderChoice::PreferredProduct);
            }
        }

        usort($usable, fn (SupplierOffer $a, SupplierOffer $b): int => [$a->sellingPrice, $a->price->getCost(), $a->price->getId()] <=> [$b->sellingPrice, $b->price->getCost(), $b->price->getId()]);

        return isset($usable[0]) ? new ChosenSupplier($usable[0], ProviderChoice::Cheapest) : null;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array<int, float>> product id => provider id => commission
     */
    public function productCommissions(array $productIds): array
    {
        $commissions = [];

        foreach (ProductProvider::query()->whereIn(ProductProvider::COLUMN_PRODUCT_ID, $productIds)->whereNotNull(ProductProvider::COLUMN_COMMISSION_PERCENT)->get() as $row) {
            $commissions[$row->getProductId()][$row->getProviderId()] = (float) $row->getCommissionPercent();
        }

        return $commissions;
    }

    /**
     * @param  list<int>  $brandIds
     * @return array<int, array<int, float>> brand id => provider id => commission
     */
    public function brandCommissions(array $brandIds): array
    {
        $commissions = [];

        foreach (BrandProvider::query()->whereIn(BrandProvider::COLUMN_BRAND_ID, $brandIds)->get() as $row) {
            $commissions[$row->getBrandId()][$row->getProviderId()] = $row->getCommissionPercent();
        }

        return $commissions;
    }

    /**
     * @param  array<int, VariantPricingInput>  $inputs
     * @return array<int, int> product id => preferred provider id
     */
    private function preferredByProduct(array $inputs): array
    {
        $productIds = array_values(array_unique(array_map(fn (VariantPricingInput $input): int => $input->productId, $inputs)));

        return array_map(intval(...), ProductProvider::query()
            ->whereIn(ProductProvider::COLUMN_PRODUCT_ID, $productIds)
            ->where(ProductProvider::COLUMN_IS_PREFERRED, true)
            ->pluck(ProductProvider::COLUMN_PROVIDER_ID, ProductProvider::COLUMN_PRODUCT_ID)
            ->all());
    }
}
