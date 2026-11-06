<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\ActivityLogs;

use App\Modules\System\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Modules\System\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Modules\System\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use App\Modules\System\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only audit trail of admin changes (roadmap D16), for super admins.
 */
final class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'فعالیت';

    protected static ?string $pluralModelLabel = 'گزارش فعالیت‌ها';

    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
