<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Tables;

use App\Modules\Orders\Actions\InquireOrderReturn;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Enums\ReturnScope;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\Toman;
use App\Modules\Shipping\Enums\TipaxParcelStage;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Order returns (S27): the return, its order (opens the order), full or partial, status, Tipax's latest status (with
 * «استعلام از تیپاکس» on each row), refund and Tipax's price. Also the table of «مرجوعی‌ها» on the order's page, without
 * the order column.
 */
final class OrderReturnsTable
{
    public static function configure(Table $table, bool $withOrder = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('order'))
            ->defaultSort(OrderReturn::COLUMN_ID, 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('number')
                    ->label('مرجوعی')
                    ->state(fn (OrderReturn $record): string => $record->getNumber())
                    ->weight('bold')
                    ->extraAttributes(['dir' => 'ltr']),
                $withOrder ? TextColumn::make('order.'.Order::COLUMN_NUMBER)
                    ->label('سفارش')
                    ->url(fn (OrderReturn $record): string => OrderResource::getUrl('view', ['record' => $record->getOrderId()]))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr']) : null,
                TextColumn::make('scope')
                    ->label('نوع')
                    ->badge()
                    ->state(fn (OrderReturn $record): string => $record->getScope()->label())
                    ->color(fn (OrderReturn $record): string => $record->getScope()->color()),
                TextColumn::make(OrderReturn::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (OrderReturnStatus $state): string => $state->label())
                    ->color(fn (OrderReturnStatus $state): string => $state->color()),
                TextColumn::make(OrderReturn::COLUMN_TIPAX_STAGE)
                    ->label('آخرین وضعیت تیپاکس')
                    ->badge()
                    ->state(fn (OrderReturn $record): ?string => $record->needsPickup() ? $record->getTipaxStage()?->label() : 'بدون جمع‌آوری')
                    ->color(fn (OrderReturn $record): string => match ($record->getTipaxStage()) {
                        TipaxParcelStage::PickedUp => 'info',
                        TipaxParcelStage::Delivered => 'success',
                        TipaxParcelStage::Failed, TipaxParcelStage::SentBack => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (OrderReturn $record): ?string => $record->getTipaxCheckedAt() === null
                        ? $record->getTipaxStatus()
                        : trim(($record->getTipaxStatus() ?? '').' · '.JalaliDate::display($record->getTipaxCheckedAt(), 'j F، H:i'), ' ·'))
                    ->placeholder('پس از تأیید'),
                TextColumn::make('refund')
                    ->label('مبلغ بازگشتی')
                    ->state(fn (OrderReturn $record): string => Toman::format($record->getRefundTotal())),
                TextColumn::make(OrderReturn::COLUMN_RETURN_SHIPPING_PRICE)
                    ->label('هزینه ارسال مرجوعی')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                    ->placeholder('قیمت گرفته نشد'),
                TextColumn::make(OrderReturn::COLUMN_CREATED_AT)
                    ->label('ثبت')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])))
            ->filters([
                SelectFilter::make(OrderReturn::COLUMN_STATUS)->label('وضعیت')->options(OrderReturnStatus::options()),
                SelectFilter::make(OrderReturn::COLUMN_IS_FULL)
                    ->label('نوع')
                    ->options([1 => ReturnScope::Full->label(), 0 => ReturnScope::Partial->label()]),
            ])
            ->recordActions([
                // Per return (owner, 2026-11-19): ask Tipax now where its parcels are.
                Action::make('inquire')
                    ->label('استعلام از تیپاکس')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->color('gray')
                    ->visible(fn (OrderReturn $record): bool => $record->needsPickup() && $record->getTrackingCodes() !== [])
                    ->action(function (OrderReturn $record, InquireOrderReturn $inquire): void {
                        try {
                            $progress = $inquire($record);
                        } catch (TipaxRequestFailed $failed) {
                            Notification::make()->danger()->title('تیپاکس پاسخ نداد.')->body($failed->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()
                            ->title($record->getNumber().': '.($progress?->stage->label() ?? 'نامشخص'))
                            ->body($progress?->status)
                            ->send();
                    }),
            ])
            ->recordUrl(fn (OrderReturn $record): string => OrderReturnResource::getUrl('view', ['record' => $record]));
    }
}
