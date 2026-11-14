<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\PriceImports\Pages;

use App\Modules\Pricing\Filament\Resources\PriceImports\PriceImportResource;
use Filament\Resources\Pages\ListRecords;

final class ListPriceImports extends ListRecords
{
    protected static string $resource = PriceImportResource::class;
}
