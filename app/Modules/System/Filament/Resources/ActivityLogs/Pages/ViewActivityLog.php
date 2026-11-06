<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\ActivityLogs\Pages;

use App\Modules\System\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;
}
