<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Queries;

use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Shared\Support\Toman;
use Carbon\CarbonImmutable;

/**
 * Suppliers for other modules' admin forms (roadmap S30): their names, and a supplier's current cost of a SKU, which
 * the order purchase form shows only as a hint (the admin types what was really paid).
 */
final readonly class SupplierOptions
{
    /**
     * Active suppliers, by name.
     *
     * @return array<int, string> id => name
     */
    public function names(): array
    {
        /** @var array<int, string> */
        return Provider::query()
            ->where(Provider::COLUMN_IS_ACTIVE, true)
            ->orderBy(Provider::COLUMN_NAME)
            ->pluck(Provider::COLUMN_NAME, Provider::COLUMN_ID)
            ->all();
    }

    /**
     * The suppliers carrying a SKU with their current cost, cheapest first: «پخش البرز — ۴۵٬۰۰۰ تومان».
     *
     * @return array<int, string> id => label
     */
    public function forVariant(int $variantId): array
    {
        $options = [];

        foreach (ProviderPrice::query()->with('provider')->where(ProviderPrice::COLUMN_VARIANT_ID, $variantId)->orderBy(ProviderPrice::COLUMN_COST)->get() as $price) {
            $options[$price->getProviderId()] = ($price->getProvider()?->getName() ?? '#'.$price->getProviderId()).' — '.Toman::format($price->getCost());
        }

        return $options;
    }

    /**
     * The name of a supplier, active or not; null when there is none.
     */
    public function name(int $providerId): ?string
    {
        $name = Provider::query()->whereKey($providerId)->value(Provider::COLUMN_NAME);

        return $name === null ? null : (string) $name;
    }

    /**
     * The supplier's current cost of the SKU (rial), or null when it has no row for it.
     */
    public function cost(int $providerId, int $variantId): ?int
    {
        $cost = ProviderPrice::query()
            ->where(ProviderPrice::COLUMN_PROVIDER_ID, $providerId)
            ->where(ProviderPrice::COLUMN_VARIANT_ID, $variantId)
            ->value(ProviderPrice::COLUMN_COST);

        return $cost === null ? null : (int) $cost;
    }

    /**
     * Whether a supplier's stock of a SKU is «زودمصرف» and its expiry date (owner, 2026-11-17); not near expiry when the
     * supplier has no row for it.
     *
     * @return array{0: bool, 1: CarbonImmutable|null}
     */
    public function nearExpiry(int $providerId, int $variantId): array
    {
        $price = ProviderPrice::query()
            ->where(ProviderPrice::COLUMN_PROVIDER_ID, $providerId)
            ->where(ProviderPrice::COLUMN_VARIANT_ID, $variantId)
            ->first();

        return $price instanceof ProviderPrice && $price->isNearExpiry() ? [true, $price->getExpiryDate()] : [false, null];
    }
}
