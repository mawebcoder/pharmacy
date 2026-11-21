<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\Payments;

use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Queries\OrderNotice;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Filament\Resources\Payments\Pages\ListPayments;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * «پرداخت‌ها» (owner, 2026-11-19): every paid payment from the gateway, an order's own or the difference after an edit,
 * with its order. Gateway payments are never approved by hand; failed and expired attempts stay on the order's page.
 */
final class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'پرداخت‌ها';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'پرداخت';

    protected static ?string $pluralModelLabel = 'پرداخت‌ها';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn(Payment::COLUMN_KIND, [PaymentKind::Order, PaymentKind::Difference])
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Paid);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(Payment::COLUMN_VERIFIED_AT, 'desc')
            ->columns([
                TextColumn::make(Payment::COLUMN_VERIFIED_AT)
                    ->label('زمان پرداخت')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make(Payment::COLUMN_ORDER_ID)
                    ->label('سفارش')
                    ->formatStateUsing(fn (int $state): string => app(OrderNotice::class)->for($state)['number'] ?? '#'.$state)
                    ->description(fn (Payment $record): string => app(OrderNotice::class)->for($record->getOrderId())['name'] ?? '')
                    ->url(fn (Payment $record): string => OrderResource::getUrl('view', ['record' => $record->getOrderId()]))
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make(Payment::COLUMN_KIND)
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (PaymentKind $state): string => $state->label())
                    ->color(fn (PaymentKind $state): string => $state->color()),
                TextColumn::make(Payment::COLUMN_AMOUNT)->label('مبلغ')->formatStateUsing(fn (int $state): string => Toman::format($state))->sortable(),
                TextColumn::make(Payment::COLUMN_REF_ID)->label('کد پیگیری')->copyable()->extraAttributes(['dir' => 'ltr'])->searchable(),
                TextColumn::make(Payment::COLUMN_CARD_PAN)
                    ->label('کارت')
                    ->formatStateUsing(fn (string $state): string => PersianDigits::toPersian($state))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—'),
                TextColumn::make(Payment::COLUMN_FEE)
                    ->label('کارمزد درگاه')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make(Payment::COLUMN_KIND)->label('نوع')->options([
                    PaymentKind::Order->value => PaymentKind::Order->label(),
                    PaymentKind::Difference->value => PaymentKind::Difference->label(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
