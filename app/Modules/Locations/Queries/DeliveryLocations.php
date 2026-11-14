<?php

declare(strict_types=1);

namespace App\Modules\Locations\Queries;

use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use Illuminate\Database\Eloquent\Builder;

/**
 * Places a customer may pick for a delivery address (roadmap S18), for other modules' selects and checks: only
 * cities with delivery switched on («ارسال فعال», S05) and their provinces. Cities are Tipax's (ids are Tipax city
 * ids), so no mapping is needed.
 */
final readonly class DeliveryLocations
{
    /**
     * Provinces that have at least one delivery city: id => name.
     *
     * @return array<int, string>
     */
    public function provinces(): array
    {
        return Province::query()
            ->whereHas('cities', fn (Builder $cities): Builder => $cities->where(City::COLUMN_IS_DELIVERY_ENABLED, true))
            ->orderBy(Province::COLUMN_NAME_FA)
            ->pluck(Province::COLUMN_NAME_FA, Province::COLUMN_ID)
            ->all();
    }

    /**
     * Delivery cities of a province: id => name.
     *
     * @return array<int, string>
     */
    public function cities(?int $provinceId): array
    {
        if ($provinceId === null) {
            return [];
        }

        return City::query()
            ->where(City::COLUMN_PROVINCE_ID, $provinceId)
            ->where(City::COLUMN_IS_DELIVERY_ENABLED, true)
            ->orderBy(City::COLUMN_NAME_FA)
            ->pluck(City::COLUMN_NAME_FA, City::COLUMN_ID)
            ->all();
    }

    public function isDeliveryCity(int $provinceId, int $cityId): bool
    {
        return City::query()
            ->whereKey($cityId)
            ->where(City::COLUMN_PROVINCE_ID, $provinceId)
            ->where(City::COLUMN_IS_DELIVERY_ENABLED, true)
            ->exists();
    }

    /**
     * «تهران، تهران» style labels (city, province) for the given city ids.
     *
     * @param  list<int>  $cityIds
     * @return array<int, string>
     */
    public function cityLabels(array $cityIds): array
    {
        if ($cityIds === []) {
            return [];
        }

        return City::query()
            ->with('province:'.Province::COLUMN_ID.','.Province::COLUMN_NAME_FA)
            ->whereKey(array_values(array_unique($cityIds)))
            ->get()
            ->mapWithKeys(fn (City $city): array => [$city->getId() => $city->getNameFa().'، '.$city->province?->getNameFa()])
            ->all();
    }
}
