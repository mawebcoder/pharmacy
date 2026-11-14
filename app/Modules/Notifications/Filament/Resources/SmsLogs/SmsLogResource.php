<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Filament\Resources\SmsLogs;

use App\Modules\Notifications\Filament\Resources\SmsLogs\Pages\ListSmsLogs;
use App\Modules\Notifications\Filament\Resources\SmsLogs\Tables\SmsLogsTable;
use App\Modules\Notifications\Models\SmsLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only log of every SMS sent or tried (roadmap D11.6): provider, template, masked mobile and result.
 */
final class SmsLogResource extends Resource
{
    protected static ?string $model = SmsLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'پیامک';

    protected static ?string $pluralModelLabel = 'گزارش پیامک‌ها';

    public static function table(Table $table): Table
    {
        return SmsLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsLogs::route('/'),
        ];
    }
}
