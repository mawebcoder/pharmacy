<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Customers\Pages;

use App\Modules\Identity\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

final class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
