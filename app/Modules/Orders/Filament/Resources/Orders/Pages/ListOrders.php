<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
