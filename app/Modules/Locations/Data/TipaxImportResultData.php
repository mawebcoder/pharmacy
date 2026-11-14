<?php

declare(strict_types=1);

namespace App\Modules\Locations\Data;

/**
 * What `locations:import-tipax` did: provinces and cities in the file, cities new to us, and our cities Tipax no
 * longer lists (kept, because addresses may use them).
 */
final readonly class TipaxImportResultData
{
    public function __construct(
        public int $provinces,
        public int $cities,
        public int $newCities,
        public int $notInTipax,
    ) {}
}
