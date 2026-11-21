<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\Payments\Pages;

use App\Modules\Payments\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

final class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
