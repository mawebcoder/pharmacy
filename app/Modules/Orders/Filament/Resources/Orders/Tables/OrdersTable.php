<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Tables;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\PersianNormalizer;
use App\Modules\Shared\Support\Toman;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The orders list (roadmap S29): newest first, from the order's own copies (customer and address snapshots), found by
 * number or mobile, filtered by status, payment and date. A row opens the order's page.
 */
final class OrdersTable
{
    /**
     * Also the customer page's «سفارش‌ها» tab (CustomerOrdersRelationManager).
     *
     * @return list<TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make(Order::COLUMN_NUMBER)
                ->label('شماره سفارش')
                ->extraAttributes(['dir' => 'ltr'])
                ->weight('bold')
                ->copyable()
                ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search)),
            TextColumn::make(Order::COLUMN_PAID_AT)
                ->label('تاریخ پرداخت')
                ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                ->placeholder('پرداخت نشده')
                ->sortable(),
            TextColumn::make(Order::COLUMN_CUSTOMER_SNAPSHOT)
                ->label('مشتری')
                ->state(fn (Order $record): string => (string) ($record->getCustomerSnapshot()['name'] ?? '') ?: '—')
                ->description(fn (Order $record): string => PersianDigits::toPersian((string) ($record->getCustomerSnapshot()['mobile'] ?? ''))),
            TextColumn::make(Order::COLUMN_STATUS)
                ->label('وضعیت')
                ->badge()
                ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                ->color(fn (OrderStatus $state): string => $state->color()),
            TextColumn::make('items_count')
                ->label('اقلام')
                ->counts('items')
                ->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
            TextColumn::make(Order::COLUMN_TOTAL)
                ->label('مبلغ')
                ->formatStateUsing(fn (int $state): string => Toman::format($state))
                ->sortable(),
            TextColumn::make(Order::COLUMN_PAYMENT_STATUS)
                ->label('پرداخت')
                ->badge()
                ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                ->color(fn (PaymentStatus $state): string => $state->color()),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort(Order::COLUMN_PLACED_AT, 'desc')
            ->filters([
                SelectFilter::make(Order::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->multiple()
                    ->options(OrderStatus::class),
                SelectFilter::make(Order::COLUMN_PAYMENT_STATUS)
                    ->label('پرداخت')
                    ->multiple()
                    ->options(PaymentStatus::class),
                Filter::make('paid_between')
                    ->label('تاریخ پرداخت')
                    ->schema([
                        DatePicker::make('from')->label('از تاریخ')->jalali(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        DatePicker::make('until')->label('تا تاریخ')->jalali(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate(Order::COLUMN_PAID_AT, '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate(Order::COLUMN_PAID_AT, '<=', $until))),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]));
    }

    /**
     * By order number, or by the customer's or receiver's mobile in the order's copies.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private static function search(Builder $query, string $search): Builder
    {
        $digits = PersianNormalizer::digits(trim($search));
        $mobile = IranMobile::normalize($digits);

        return $query->where(fn (Builder $inner): Builder => $inner
            ->where(Order::COLUMN_NUMBER, 'like', '%'.strtoupper($digits).'%')
            ->when($mobile !== null, fn (Builder $byMobile): Builder => $byMobile
                ->orWhere(Order::COLUMN_CUSTOMER_SNAPSHOT.'->mobile', $mobile)
                ->orWhere(Order::COLUMN_ADDRESS_SNAPSHOT.'->receiver_mobile', $mobile)));
    }
}
