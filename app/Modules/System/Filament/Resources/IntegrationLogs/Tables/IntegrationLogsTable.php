<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\IntegrationLogs\Tables;

use App\Modules\System\Models\IntegrationLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class IntegrationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(IntegrationLog::COLUMN_CREATED_AT)
                    ->label('زمان')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make(IntegrationLog::COLUMN_SERVICE)
                    ->label('سرویس')
                    ->badge()
                    ->searchable(),
                TextColumn::make(IntegrationLog::COLUMN_OPERATION)
                    ->label('عملیات')
                    ->searchable(),
                TextColumn::make(IntegrationLog::COLUMN_HTTP_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->placeholder('خطای اتصال')
                    ->color(fn (IntegrationLog $record): string => $record->isFailure() ? 'danger' : 'success'),
                TextColumn::make(IntegrationLog::COLUMN_DURATION_MS)
                    ->label('مدت (میلی‌ثانیه)')
                    ->sortable(),
                TextColumn::make(IntegrationLog::COLUMN_ORDER_ID)
                    ->label('سفارش')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make(IntegrationLog::COLUMN_CORRELATION_ID)
                    ->label('شناسه پیگیری')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make(IntegrationLog::COLUMN_SERVICE)
                    ->label('سرویس')
                    ->options(fn (): array => IntegrationLog::query()->distinct()->orderBy(IntegrationLog::COLUMN_SERVICE)->pluck(IntegrationLog::COLUMN_SERVICE, IntegrationLog::COLUMN_SERVICE)->all()),
                Filter::make('failed')
                    ->label('فقط ناموفق')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->whereNull(IntegrationLog::COLUMN_HTTP_STATUS)
                        ->orWhere(IntegrationLog::COLUMN_HTTP_STATUS, '>=', 400))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort(IntegrationLog::COLUMN_CREATED_AT, 'desc');
    }
}
