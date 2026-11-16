<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Purchases\Tables;

use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Support\PurchaseMargin;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Purchase documents (S30): the document, its order (opens the order), status, rows, cost and the final margin.
 */
final class OrderPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['order.purchase'])->withCount('items'))
            ->defaultSort(OrderPurchase::COLUMN_ID, 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('سند خرید')
                    ->state(fn (OrderPurchase $record): string => $record->getNumber())
                    ->weight('bold')
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('order.'.Order::COLUMN_NUMBER)
                    ->label('سفارش')
                    ->url(fn (OrderPurchase $record): string => OrderResource::getUrl('view', ['record' => $record->getOrderId()]))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make(OrderPurchase::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseStatus $state): string => $state->label())
                    ->color(fn (PurchaseStatus $state): string => $state->color()),
                TextColumn::make('items_count')->label('ردیف‌ها')->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                TextColumn::make(OrderPurchase::COLUMN_TOTAL_COST)->label('هزینه خرید')->formatStateUsing(fn (int $state): string => Toman::format($state)),
                TextColumn::make('final_margin')
                    ->label('سود نهایی')
                    ->state(fn (OrderPurchase $record): ?int => PurchaseMargin::orderMargin($record->getOrder()))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                    ->color(fn (?int $state): ?string => $state !== null && $state < 0 ? 'danger' : null)
                    ->placeholder('—'),
                TextColumn::make(OrderPurchase::COLUMN_CREATED_AT)
                    ->label('ثبت')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make(OrderPurchase::COLUMN_STATUS)->label('وضعیت')->options(PurchaseStatus::options()),
            ])
            ->recordUrl(fn (OrderPurchase $record): string => OrderPurchaseResource::getUrl('view', ['record' => $record]));
    }
}
