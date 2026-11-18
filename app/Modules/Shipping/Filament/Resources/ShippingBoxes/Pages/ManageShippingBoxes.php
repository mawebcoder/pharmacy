<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Resources\ShippingBoxes\Pages;

use App\Modules\Shipping\Filament\Resources\ShippingBoxes\ShippingBoxResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageShippingBoxes extends ManageRecords
{
    protected static string $resource = ShippingBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [ShippingBoxResource::createAction()];
    }
}
