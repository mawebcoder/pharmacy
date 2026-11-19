<?php

declare(strict_types=1);

namespace App\Modules\Locations\Queries;

use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use Illuminate\Database\Eloquent\Builder;

/**
 * Places a customer may pick for a delivery address (roadmap S20), for other modules' selects and checks: only
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

    /**
     * Province and city names for address text (AddressText puts them first, in the Iran Post order), keyed by city id.
     *
     * @param  list<int>  $cityIds
     * @return array<int, array{province: string, city: string}>
     */
    public function placeNames(array $cityIds): array
    {
        if ($cityIds === []) {
            return [];
        }

        return City::query()
            ->with('province:'.Province::COLUMN_ID.','.Province::COLUMN_NAME_FA)
            ->whereKey(array_values(array_unique($cityIds)))
            ->get()
            ->mapWithKeys(fn (City $city): array => [$city->getId() => ['province' => (string) $city->province?->getNameFa(), 'city' => $city->getNameFa()]])
            ->all();
    }

    /**
     * A city with its province, for order snapshots (S23): names, Tipax ids (our ids are Tipax's) and whether
     * delivery is on. Null when the city is gone.
     *
     * @return array{province_id: int, province: string, city_id: int, city: string, is_delivery_enabled: bool, latitude: float|null, longitude: float|null}|null
     */
    public function place(int $cityId): ?array
    {
        $city = City::query()->with('province')->find($cityId);

        if (! $city instanceof City) {
            return null;
        }

        return [
            'province_id' => $city->getProvinceId(),
            'province' => (string) $city->province?->getNameFa(),
            'city_id' => $city->getId(),
            'city' => $city->getNameFa(),
            'is_delivery_enabled' => $city->isDeliveryEnabled(),
            'latitude' => $city->getLatitude(),
            'longitude' => $city->getLongitude(),
        ];
    }
}
