<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\IntegrationLogs\Schemas;

use App\Modules\System\Models\IntegrationLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Novadaemon\FilamentPrettyJson\Infolist\PrettyJsonEntry;

/**
 * One external call: what was sent (redacted) and what came back.
 */
final class IntegrationLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('جزئیات')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make(IntegrationLog::COLUMN_CREATED_AT)
                            ->label('زمان')
                            ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        TextEntry::make(IntegrationLog::COLUMN_SERVICE)->label('سرویس')->badge(),
                        TextEntry::make(IntegrationLog::COLUMN_OPERATION)->label('عملیات'),
                        TextEntry::make(IntegrationLog::COLUMN_HTTP_STATUS)
                            ->label('وضعیت')
                            ->badge()
                            ->placeholder('خطای اتصال')
                            ->color(fn (IntegrationLog $record): string => $record->isFailure() ? 'danger' : 'success'),
                        TextEntry::make(IntegrationLog::COLUMN_METHOD)->label('روش'),
                        TextEntry::make(IntegrationLog::COLUMN_URL)->label('نشانی')->columnSpan(3)->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make(IntegrationLog::COLUMN_DURATION_MS)->label('مدت (میلی‌ثانیه)'),
                        TextEntry::make(IntegrationLog::COLUMN_ORDER_ID)->label('سفارش')->placeholder('—'),
                        TextEntry::make(IntegrationLog::COLUMN_CORRELATION_ID)->label('شناسه پیگیری')->columnSpan(2),
                        TextEntry::make(IntegrationLog::COLUMN_ERROR)
                            ->label('خطا')
                            ->columnSpanFull()
                            ->visible(fn (IntegrationLog $record): bool => filled($record->getError())),
                    ]),
                Section::make('درخواست (بدون اطلاعات محرمانه)')
                    ->columnSpanFull()
                    ->schema([
                        PrettyJsonEntry::make(IntegrationLog::COLUMN_REQUEST)
                            ->hiddenLabel()
                            ->state(fn (IntegrationLog $record): ?string => $record->getRequest())
                            ->copyable()
                            ->extraAttributes(['dir' => 'ltr']),
                    ]),
                Section::make('پاسخ')
                    ->columnSpanFull()
                    ->visible(fn (IntegrationLog $record): bool => filled($record->getResponse()))
                    ->schema([
                        PrettyJsonEntry::make(IntegrationLog::COLUMN_RESPONSE)
                            ->hiddenLabel()
                            ->state(fn (IntegrationLog $record): ?string => self::responseAsJson($record))
                            ->copyable()
                            ->extraAttributes(['dir' => 'ltr']),
                    ]),
            ]);
    }

    /**
     * JSON responses are shown as is; other bodies (text, XML) are wrapped so the viewer can still display them.
     */
    private static function responseAsJson(IntegrationLog $record): ?string
    {
        $response = $record->getResponse();

        if ($response === null || json_validate($response)) {
            return $response;
        }

        return json_encode(['body' => $response], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
