<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\IntegrationLogs\Pages;

use App\Modules\System\Filament\Resources\IntegrationLogs\IntegrationLogResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewIntegrationLog extends ViewRecord
{
    protected static string $resource = IntegrationLogResource::class;
}
