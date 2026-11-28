<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories\Pages;

use App\Modules\Catalog\Filament\Resources\Subcategories\SubcategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * A subcategory read-only, with its products.
 */
final class ViewSubcategory extends ViewRecord
{
    protected static string $resource = SubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
