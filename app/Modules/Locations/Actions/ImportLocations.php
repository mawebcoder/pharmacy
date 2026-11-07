<?php

declare(strict_types=1);

namespace App\Modules\Locations\Actions;

use App\Modules\Locations\Data\LocationDatasetData;
use App\Modules\Locations\Data\LocationImportResultData;
use App\Modules\Locations\Enums\CityType;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\County;
use App\Modules\Locations\Models\Province;
use Illuminate\Support\Facades\DB;

/**
 * Inserts or updates provinces, counties and cities by their dataset id (source_id). Safe to run again:
 * names and coordinates are refreshed, but the admin-controlled delivery flag is never touched.
 */
final class ImportLocations
{
    private const int CHUNK = 500;

    #[\NoDiscard]
    public function __invoke(LocationDatasetData $dataset): LocationImportResultData
    {
        return DB::transaction(function () use ($dataset): LocationImportResultData {
            $now = now();

            foreach (array_chunk($dataset->provinces, self::CHUNK) as $chunk) {
                Province::query()->upsert(array_map(fn (array $row): array => [
                    Province::COLUMN_SOURCE_ID => $row['id'],
                    Province::COLUMN_NAME_FA => $row['name'],
                    Province::COLUMN_SLUG => $row['slug'],
                    Province::COLUMN_TEL_PREFIX => $row['tel_prefix'],
                    Province::COLUMN_CREATED_AT => $now,
                    Province::COLUMN_UPDATED_AT => $now,
                ], $chunk), [Province::COLUMN_SOURCE_ID], [Province::COLUMN_NAME_FA, Province::COLUMN_SLUG, Province::COLUMN_TEL_PREFIX, Province::COLUMN_UPDATED_AT]);
            }

            /** @var array<int, int> $provinceIds */
            $provinceIds = Province::query()->pluck(Province::COLUMN_ID, Province::COLUMN_SOURCE_ID)->all();

            foreach (array_chunk($dataset->counties, self::CHUNK) as $chunk) {
                County::query()->upsert(array_map(fn (array $row): array => [
                    County::COLUMN_SOURCE_ID => $row['id'],
                    County::COLUMN_PROVINCE_ID => $provinceIds[$row['province_id']],
                    County::COLUMN_NAME_FA => $row['name'],
                    County::COLUMN_SLUG => $row['slug'],
                    County::COLUMN_CREATED_AT => $now,
                    County::COLUMN_UPDATED_AT => $now,
                ], $chunk), [County::COLUMN_SOURCE_ID], [County::COLUMN_PROVINCE_ID, County::COLUMN_NAME_FA, County::COLUMN_SLUG, County::COLUMN_UPDATED_AT]);
            }

            /** @var array<int, int> $countyIds */
            $countyIds = County::query()->pluck(County::COLUMN_ID, County::COLUMN_SOURCE_ID)->all();
            $centers = $this->countyCentersByName($dataset);
            $withCoordinates = 0;

            foreach (array_chunk($dataset->cities, self::CHUNK) as $chunk) {
                City::query()->upsert(array_map(function (array $row) use ($provinceIds, $countyIds, $centers, $now, &$withCoordinates): array {
                    $center = $centers[$row['county_id']][$row['name']] ?? null;
                    $withCoordinates += $center === null ? 0 : 1;

                    return [
                        City::COLUMN_SOURCE_ID => $row['id'],
                        City::COLUMN_PROVINCE_ID => $provinceIds[$row['province_id']],
                        City::COLUMN_COUNTY_ID => $countyIds[$row['county_id']],
                        City::COLUMN_NAME_FA => $row['name'],
                        City::COLUMN_SLUG => $row['slug'],
                        City::COLUMN_TYPE => CityType::City->value,
                        City::COLUMN_LATITUDE => $center['latitude'] ?? null,
                        City::COLUMN_LONGITUDE => $center['longitude'] ?? null,
                        City::COLUMN_IS_DELIVERY_ENABLED => false,
                        City::COLUMN_CREATED_AT => $now,
                        City::COLUMN_UPDATED_AT => $now,
                    ];
                }, $chunk), [City::COLUMN_SOURCE_ID], [
                    City::COLUMN_PROVINCE_ID, City::COLUMN_COUNTY_ID, City::COLUMN_NAME_FA, City::COLUMN_SLUG,
                    City::COLUMN_TYPE, City::COLUMN_LATITUDE, City::COLUMN_LONGITUDE, City::COLUMN_UPDATED_AT,
                ]);
            }

            return new LocationImportResultData(
                provinces: count($dataset->provinces),
                counties: count($dataset->counties),
                cities: count($dataset->cities),
                citiesWithCoordinates: $withCoordinates,
            );
        });
    }

    /**
     * County centre coordinates keyed by county source id and the centre's city name.
     *
     * @return array<int, array<string, array{latitude: float, longitude: float}>>
     */
    private function countyCentersByName(LocationDatasetData $dataset): array
    {
        $centers = [];

        foreach ($dataset->countyCenters as $center) {
            $centers[$center['county_id']][$center['center_name']] = [
                'latitude' => (float) $center['latitude'],
                'longitude' => (float) $center['longitude'],
            ];
        }

        return $centers;
    }
}
