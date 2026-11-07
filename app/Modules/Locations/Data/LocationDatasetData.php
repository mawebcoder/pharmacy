<?php

declare(strict_types=1);

namespace App\Modules\Locations\Data;

use App\Modules\Locations\Exceptions\InvalidLocationDataset;

/**
 * The validated rows of one copy of the location dataset (roadmap D4.1).
 */
final readonly class LocationDatasetData
{
    /**
     * @param  list<array{id: int, name: string, slug: string, tel_prefix: string|null}>  $provinces
     * @param  list<array{id: int, name: string, slug: string, province_id: int}>  $counties
     * @param  list<array{id: int, name: string, slug: string, province_id: int, county_id: int}>  $cities
     * @param  list<array{county_id: int, center_name: string, latitude: float, longitude: float}>  $countyCenters
     */
    public function __construct(
        public array $provinces,
        public array $counties,
        public array $cities,
        public array $countyCenters,
    ) {}

    /**
     * Reads provinces.json, counties.json, cities.json and county-centers.json from a directory.
     *
     * @throws InvalidLocationDataset
     */
    public static function fromDirectory(string $directory): self
    {
        $provinces = self::rows($directory, 'provinces.json', ['id' => 'int', 'name' => 'string', 'slug' => 'string']);
        $counties = self::rows($directory, 'counties.json', ['id' => 'int', 'name' => 'string', 'slug' => 'string', 'province_id' => 'int']);
        $cities = self::rows($directory, 'cities.json', ['id' => 'int', 'name' => 'string', 'slug' => 'string', 'province_id' => 'int', 'county_id' => 'int']);
        $centers = self::rows($directory, 'county-centers.json', ['county_id' => 'int', 'center_name' => 'string', 'latitude' => 'number', 'longitude' => 'number']);

        $provinceIds = array_flip(array_column($provinces, 'id'));
        $countyIds = array_flip(array_column($counties, 'id'));

        foreach ($counties as $index => $county) {
            if (! isset($provinceIds[$county['province_id']])) {
                throw InvalidLocationDataset::unknownParent('counties.json', $index, 'province_id', $county['province_id']);
            }
        }

        foreach ($cities as $index => $city) {
            if (! isset($provinceIds[$city['province_id']])) {
                throw InvalidLocationDataset::unknownParent('cities.json', $index, 'province_id', $city['province_id']);
            }

            if (! isset($countyIds[$city['county_id']])) {
                throw InvalidLocationDataset::unknownParent('cities.json', $index, 'county_id', $city['county_id']);
            }
        }

        /** @var list<array{id: int, name: string, slug: string, tel_prefix: string|null}> $provinces */
        $provinces = array_map(fn (array $row): array => [...$row, 'tel_prefix' => isset($row['tel_prefix']) && is_string($row['tel_prefix']) ? $row['tel_prefix'] : null], $provinces);

        /** @var list<array{id: int, name: string, slug: string, province_id: int}> $counties */
        /** @var list<array{id: int, name: string, slug: string, province_id: int, county_id: int}> $cities */
        /** @var list<array{county_id: int, center_name: string, latitude: float, longitude: float}> $centers */
        return new self($provinces, $counties, $cities, $centers);
    }

    /**
     * @param  array<string, 'int'|'string'|'number'>  $required
     * @return list<array<string, mixed>>
     *
     * @throws InvalidLocationDataset
     */
    private static function rows(string $directory, string $file, array $required): array
    {
        $path = rtrim($directory, '/').'/'.$file;

        if (! is_file($path)) {
            throw InvalidLocationDataset::missingFile($path);
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw InvalidLocationDataset::invalidJson($path);
        }

        foreach ($rows as $index => $row) {
            foreach ($required as $field => $type) {
                $value = is_array($row) ? ($row[$field] ?? null) : null;
                $valid = match ($type) {
                    'int' => is_int($value),
                    'string' => is_string($value) && trim($value) !== '',
                    'number' => is_int($value) || is_float($value),
                };

                if (! $valid) {
                    throw InvalidLocationDataset::invalidRow($file, $index, $field);
                }
            }
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }
}
