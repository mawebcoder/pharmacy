<?php

declare(strict_types=1);

namespace App\Modules\Locations\Filament\Resources\Cities\Pages;

use App\Modules\Locations\Filament\Resources\Cities\CityResource;
use Filament\Resources\Pages\ListRecords;

final class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;
}
