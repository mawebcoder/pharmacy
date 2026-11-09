<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Collections\Pages;

use App\Modules\Catalog\Filament\Resources\Collections\CollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
