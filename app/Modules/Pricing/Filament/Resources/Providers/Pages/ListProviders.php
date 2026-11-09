<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\Providers\Pages;

use App\Modules\Pricing\Filament\Resources\Providers\ProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListProviders extends ListRecords
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
