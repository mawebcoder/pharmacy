<?php

declare(strict_types=1);

namespace App\Modules\Locations\Actions;

use App\Modules\Locations\Data\TipaxCityData;
use App\Modules\Locations\Data\TipaxImportResultData;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use Illuminate\Support\Facades\DB;

/**
 * Brings provinces and cities in line with Tipax (roadmap D4.3, decided 2026-11-14 in S18): both keyed by Tipax's
 * ids (stateId, city id). Names and branch flags are updated; «ارسال فعال» is kept. Cities Tipax no
 * longer lists are kept and counted. One activity entry per import, not one per row.
 */
final class ImportTipaxCities
{
    /**
     * @param  list<TipaxCityData>  $cities
     * @param  list<int>|null  $branchCityIds  from BranchCities; null keeps the current branch flags
     */
    #[\NoDiscard]
    public function __invoke(array $cities, ?array $branchCityIds = null): TipaxImportResultData
    {
        $now = now();
        $branches = $branchCityIds === null ? null : array_flip($branchCityIds);

        $result = DB::transaction(function () use ($cities, $branches, $now): TipaxImportResultData {
            $provinces = [];

            foreach ($cities as $city) {
                $provinces[$city->tipaxStateId] = $city->provinceNameFa;
            }

            Province::query()->upsert(array_map(fn (int $stateId, string $name): array => [
                Province::COLUMN_ID => $stateId,
                Province::COLUMN_NAME_FA => $name,
                Province::COLUMN_CREATED_AT => $now,
                Province::COLUMN_UPDATED_AT => $now,
            ], array_keys($provinces), $provinces), [Province::COLUMN_ID], [Province::COLUMN_NAME_FA, Province::COLUMN_UPDATED_AT]);

            $tipaxIds = array_map(fn (TipaxCityData $city): int => $city->tipaxCityId, $cities);
            $existing = City::query()->whereKey($tipaxIds)->pluck(City::COLUMN_HAS_TIPAX_BRANCH, City::COLUMN_ID);

            foreach (array_chunk($cities, 500) as $chunk) {
                City::query()->upsert(array_map(fn (TipaxCityData $city): array => [
                    City::COLUMN_ID => $city->tipaxCityId,
                    City::COLUMN_PROVINCE_ID => $city->tipaxStateId,
                    City::COLUMN_NAME_FA => $city->nameFa,
                    City::COLUMN_LATITUDE => $city->latitude,
                    City::COLUMN_LONGITUDE => $city->longitude,
                    City::COLUMN_HAS_TIPAX_BRANCH => $branches === null ? (bool) ($existing[$city->tipaxCityId] ?? true) : isset($branches[$city->tipaxCityId]),
                    City::COLUMN_TIPAX_SYNCED_AT => $now,
                    City::COLUMN_CREATED_AT => $now,
                    City::COLUMN_UPDATED_AT => $now,
                ], $chunk), [City::COLUMN_ID], [
                    City::COLUMN_PROVINCE_ID, City::COLUMN_NAME_FA, City::COLUMN_LATITUDE, City::COLUMN_LONGITUDE, City::COLUMN_HAS_TIPAX_BRANCH, City::COLUMN_TIPAX_SYNCED_AT, City::COLUMN_UPDATED_AT,
                ]);
            }

            return new TipaxImportResultData(
                provinces: count($provinces),
                cities: count($cities),
                newCities: count($cities) - $existing->count(),
                notInTipax: City::query()->whereKeyNot($tipaxIds)->count(),
            );
        });

        activity('locations')
            ->event('tipax_cities_imported')
            ->withProperties(['provinces' => $result->provinces, 'cities' => $result->cities, 'new' => $result->newCities, 'not_in_tipax' => $result->notInTipax])
            ->log('tipax_cities_imported');

        return $result;
    }
}
