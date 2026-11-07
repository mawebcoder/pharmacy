<?php

declare(strict_types=1);

namespace App\Modules\Locations\Data;

final readonly class LocationImportResultData
{
    public function __construct(
        public int $provinces,
        public int $counties,
        public int $cities,
        public int $citiesWithCoordinates,
    ) {}
}
