<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories\Pages;

use App\Modules\Catalog\Filament\Resources\Subcategories\SubcategoryResource;
use Filament\Resources\Pages\ListRecords;

final class ListSubcategories extends ListRecords
{
    protected static string $resource = SubcategoryResource::class;
}
