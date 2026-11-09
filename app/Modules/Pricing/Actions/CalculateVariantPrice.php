<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Data\VariantPricingInput;
use App\Modules\Pricing\Data\CalculatedPrice;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;

/**
 * The pricing engine (roadmap D3.2): selling price = active supplier's cost × (1 + commission %), rounded up to
 * 1,000 toman, never above the printed price; the haraj is kept only while it is below the selling price.
 *
 * Example: cost 850,000 toman, commission 10 % → 935,000 toman; printed 900,000 → 900,000 (capped, flagged).
 *
 * Without an active supplier (none at all, or none enabled, available and active), or without a commission, the SKU
 * is sold at its printed price (owner, 2026-11-09). Without a printed price either, there is nothing to sell at: the
 * current price is kept and the SKU is out of stock (see isSellable()).
 */
final class CalculateVariantPrice
{
    /**
     * 1,000 toman.
     */
    public const int ROUNDING_RIAL = 10_000;

    /**
     * A move above this share of the previous price is flagged in the history.
     */
    public const float FLAG_CHANGE_SHARE = 0.15;

    public function __invoke(VariantPricingInput $input, ?ProviderPrice $activeSupplier, ?VariantPrice $latest): CalculatedPrice
    {
        $flags = [];
        $commission = $input->commissionPercent;
        $source = $input->commissionSource === null ? null : CommissionSource::from($input->commissionSource);

        if ($activeSupplier instanceof ProviderPrice && $commission !== null) {
            $providerId = $activeSupplier->getProviderId();
            $providerCost = $activeSupplier->getCost();
            $price = self::sellingPrice($providerCost, $commission);

            if ($input->officialPrice !== null && $input->officialPrice > 0 && $price > $input->officialPrice) {
                $price = $input->officialPrice;
                $flags[] = VariantPrice::FLAG_CAPPED_BY_PRINTED;
            }
        } elseif ($input->officialPrice !== null && $input->officialPrice > 0) {
            $providerId = null;
            $providerCost = null;
            $price = $input->officialPrice;
            $flags[] = VariantPrice::FLAG_SOLD_AT_PRINTED;
        } else {
            $providerId = null;
            $providerCost = null;
            $price = $input->price;
        }

        $salePrice = $input->salePrice;

        if ($salePrice !== null && $salePrice >= $price) {
            $salePrice = null;
            $flags[] = VariantPrice::FLAG_HARAJ_REMOVED;
        }

        $previous = $latest?->getPrice() ?? $input->price;

        if ($previous > 0 && abs($price - $previous) / $previous > self::FLAG_CHANGE_SHARE) {
            $flags[] = VariantPrice::FLAG_CHANGE_OVER_15_PERCENT;
        }

        return new CalculatedPrice(
            officialPrice: $input->officialPrice,
            providerId: $providerId,
            providerCost: $providerCost,
            commissionPercent: $commission,
            commissionSource: $source,
            price: $price,
            salePrice: $salePrice,
            flags: $flags,
        );
    }

    /**
     * Whether a calculated price can be sold at: from an active supplier, or at the printed price.
     */
    public static function isSellable(CalculatedPrice $calculated): bool
    {
        return $calculated->providerId !== null || in_array(VariantPrice::FLAG_SOLD_AT_PRINTED, $calculated->flags, true);
    }

    /**
     * cost × (1 + commission %), rounded up to 1,000 toman. Rial in, Rial out.
     */
    public static function sellingPrice(int $cost, float $commissionPercent): int
    {
        $raw = (int) ceil(round($cost * (1 + $commissionPercent / 100), 2));

        return (int) (ceil($raw / self::ROUNDING_RIAL) * self::ROUNDING_RIAL);
    }

    /**
     * Whether a calculation holds anything the latest record does not (only then a new record is written).
     */
    public static function differs(CalculatedPrice $calculated, ?VariantPrice $latest): bool
    {
        return ! $latest instanceof VariantPrice
            || $latest->getOfficialPrice() !== $calculated->officialPrice
            || $latest->getProviderId() !== $calculated->providerId
            || $latest->getProviderCost() !== $calculated->providerCost
            || $latest->getCommissionPercent() !== $calculated->commissionPercent
            || $latest->getCommissionSource() !== $calculated->commissionSource
            || $latest->getPrice() !== $calculated->price
            || $latest->getSalePrice() !== $calculated->salePrice;
    }
}
