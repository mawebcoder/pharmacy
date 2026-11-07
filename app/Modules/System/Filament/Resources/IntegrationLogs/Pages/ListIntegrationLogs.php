<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\IntegrationLogs\Pages;

use App\Modules\System\Filament\Resources\IntegrationLogs\IntegrationLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListIntegrationLogs extends ListRecords
{
    protected static string $resource = IntegrationLogResource::class;
}
