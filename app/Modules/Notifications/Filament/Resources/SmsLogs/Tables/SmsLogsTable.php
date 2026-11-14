<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Filament\Resources\SmsLogs\Tables;

use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Models\SmsLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SmsLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(SmsLog::COLUMN_CREATED_AT)
                    ->label('زمان')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make(SmsLog::COLUMN_RECIPIENT)
                    ->label('گیرنده')
                    ->extraAttributes(['dir' => 'ltr'])
                    ->searchable(),
                TextColumn::make(SmsLog::COLUMN_TEMPLATE)
                    ->label('الگو')
                    ->formatStateUsing(fn (SmsTemplate $state): string => $state->label())
                    ->badge(),
                TextColumn::make(SmsLog::COLUMN_PROVIDER)
                    ->label('سامانه')
                    ->formatStateUsing(fn (SmsLog $record): string => $record->getProvider()->label().($record->isPretend() ? ' (آزمایشی)' : ''))
                    ->color(fn (SmsLog $record): string => $record->isPretend() ? 'gray' : 'primary')
                    ->badge(),
                IconColumn::make(SmsLog::COLUMN_SUCCESS)
                    ->label('ارسال شد')
                    ->boolean(),
                TextColumn::make(SmsLog::COLUMN_ERROR)
                    ->label('خطا')
                    ->description(fn (SmsLog $record): ?string => $record->isSuccess() ? null : 'کد '.$record->getCode())
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make(SmsLog::COLUMN_VARIABLES)
                    ->label('متغیرها')
                    ->state(fn (SmsLog $record): string => $record->getVariables() === null ? 'محرمانه' : implode('، ', $record->getVariables()))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make(SmsLog::COLUMN_PROVIDER_MESSAGE_ID)
                    ->label('شناسه پیامک')
                    ->placeholder('—')
                    ->extraAttributes(['dir' => 'ltr'])
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make(SmsLog::COLUMN_DURATION_MS)
                    ->label('مدت (میلی‌ثانیه)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make(SmsLog::COLUMN_TEMPLATE)
                    ->label('الگو')
                    ->options(SmsTemplate::options()),
                SelectFilter::make(SmsLog::COLUMN_PROVIDER)
                    ->label('سامانه')
                    ->options(SmsProvider::options()),
                TernaryFilter::make(SmsLog::COLUMN_SUCCESS)
                    ->label('نتیجه')
                    ->trueLabel('ارسال‌شده')
                    ->falseLabel('ناموفق'),
            ])
            ->defaultSort(SmsLog::COLUMN_CREATED_AT, 'desc');
    }
}
