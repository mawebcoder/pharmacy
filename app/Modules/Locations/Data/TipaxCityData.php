<?php

declare(strict_types=1);

namespace App\Modules\Locations\Data;

use App\Modules\Locations\Exceptions\InvalidTipaxCities;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * One city of Tipax's `GET /api/OM/v4/Cities/plusstate` (OmCityListWithStateDto, roadmap D4.3, decided 2026-11-14 in
 * S18): `id`, `title`, `stateId`, `stateTitle`, `latitude`, `longitude` (also `abbreviation`, `jetId`, unused).
 */
final readonly class TipaxCityData
{
    public function __construct(
        public int $tipaxCityId,
        public string $nameFa,
        public int $tipaxStateId,
        public string $provinceNameFa,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws InvalidTipaxCities
     */
    public static function fromArray(array $row, int $index): self
    {
        $id = $row['id'] ?? null;
        $stateId = $row['stateId'] ?? null;
        $name = PersianNormalizer::letters(trim((string) ($row['title'] ?? '')));
        $province = PersianNormalizer::letters(trim((string) ($row['stateTitle'] ?? '')));
        $coordinate = fn (string $key): ?float => is_numeric($row[$key] ?? null) && (float) $row[$key] !== 0.0 ? (float) $row[$key] : null;

        return new self(
            tipaxCityId: is_numeric($id) && (int) $id > 0 ? (int) $id : throw InvalidTipaxCities::invalidRow($index, 'id'),
            nameFa: $name !== '' ? $name : throw InvalidTipaxCities::invalidRow($index, 'title'),
            tipaxStateId: is_numeric($stateId) && (int) $stateId > 0 ? (int) $stateId : throw InvalidTipaxCities::invalidRow($index, 'stateId'),
            provinceNameFa: $province !== '' ? $province : throw InvalidTipaxCities::invalidRow($index, 'stateTitle'),
            latitude: $coordinate('latitude'),
            longitude: $coordinate('longitude'),
        );
    }

    /**
     * City ids from Tipax's `GET /api/OM/v4/Cities/BranchCities` (OmCityListDto).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    public static function idsOf(array $rows): array
    {
        return array_values(array_filter(array_map(fn (array $row): int => (int) ($row['id'] ?? 0), $rows)));
    }
}
