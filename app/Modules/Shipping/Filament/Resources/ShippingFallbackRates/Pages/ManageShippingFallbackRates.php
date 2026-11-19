<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources\ShippingFallbackRates\Pages;

use App\Modules\Shipping\Filament\Resources\ShippingFallbackRates\ShippingFallbackRateResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageShippingFallbackRates extends ManageRecords
{
    protected static string $resource = ShippingFallbackRateResource::class;

    protected function getHeaderActions(): array
    {
        return [ShippingFallbackRateResource::createAction()];
    }
}
