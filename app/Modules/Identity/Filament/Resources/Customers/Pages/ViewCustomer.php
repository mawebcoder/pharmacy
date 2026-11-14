<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Customers\Pages;

use App\Modules\Identity\Filament\Resources\Customers\CustomerResource;
use App\Modules\Identity\Filament\Resources\Customers\Tables\CustomersTable;
use Filament\Resources\Pages\ViewRecord;

final class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CustomersTable::blockAction(),
            CustomersTable::unblockAction(),
        ];
    }
}
