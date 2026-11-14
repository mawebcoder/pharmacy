<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Filament\Resources\SmsLogs\Pages;

use App\Modules\Notifications\Filament\Resources\SmsLogs\SmsLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListSmsLogs extends ListRecords
{
    protected static string $resource = SmsLogResource::class;
}
