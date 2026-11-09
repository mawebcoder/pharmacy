<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\Providers\Pages;

use App\Modules\Pricing\Filament\Resources\Providers\ProviderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * A supplier with its SKUs and their price information (ProviderPricesRelationManager). Its sales widgets and
 * charts come with the reports (roadmap S42).
 */
final class ViewProvider extends ViewRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
