<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Pages;

use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOrderReturns extends ListRecords
{
    protected static string $resource = OrderReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('ثبت مرجوعی')];
    }
}
