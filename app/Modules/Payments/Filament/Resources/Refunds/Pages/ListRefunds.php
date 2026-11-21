<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\Refunds\Pages;

use App\Modules\Payments\Filament\Resources\Refunds\RefundResource;
use Filament\Resources\Pages\ListRecords;

final class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;
}
