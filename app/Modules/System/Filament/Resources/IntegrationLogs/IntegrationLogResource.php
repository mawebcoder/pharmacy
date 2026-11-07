<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\IntegrationLogs;

use App\Modules\System\Filament\Resources\IntegrationLogs\Pages\ListIntegrationLogs;
use App\Modules\System\Filament\Resources\IntegrationLogs\Pages\ViewIntegrationLog;
use App\Modules\System\Filament\Resources\IntegrationLogs\Schemas\IntegrationLogInfolist;
use App\Modules\System\Filament\Resources\IntegrationLogs\Tables\IntegrationLogsTable;
use App\Modules\System\Models\IntegrationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only log of calls to external services (roadmap D0.4), for super admins.
 */
final class IntegrationLogResource extends Resource
{
    protected static ?string $model = IntegrationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'فراخوانی سرویس';

    protected static ?string $pluralModelLabel = 'گزارش سرویس‌های بیرونی';

    public static function infolist(Schema $schema): Schema
    {
        return IntegrationLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntegrationLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationLogs::route('/'),
            'view' => ViewIntegrationLog::route('/{record}'),
        ];
    }
}
