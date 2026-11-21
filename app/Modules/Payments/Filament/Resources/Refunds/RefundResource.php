<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\Refunds;

use App\Modules\Orders\Queries\OrderNotice;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Modules\Payments\Filament\Resources\Refunds\Pages\ViewRefund;
use App\Modules\Payments\Filament\Resources\Refunds\Schemas\RefundInfolist;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shared\Support\Toman;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * «بازگشت وجه‌ها» (owner, 2026-11-19): every refund owed to a customer, opened by the system from an order edit, a
 * cancelled paid order or a return received at the warehouse; each waits for an admin to approve it with the bank
 * transfer reference (the customer is texted) or reject it with a reason. Never typed in by hand, never deleted.
 */
final class RefundResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'refunds';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'پرداخت‌ها';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'بازگشت وجه';

    protected static ?string $pluralModelLabel = 'بازگشت وجه‌ها';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(Payment::COLUMN_KIND, PaymentKind::Refund);
    }

    /**
     * How many refunds wait for approval, on the menu.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = self::getEloquentQuery()->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Pending)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return RefundInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(Payment::COLUMN_ID, 'desc')
            ->columns([
                TextColumn::make(Payment::COLUMN_CREATED_AT)
                    ->label('ثبت')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make(Payment::COLUMN_ORDER_ID)
                    ->label('سفارش')
                    ->formatStateUsing(fn (int $state): string => app(OrderNotice::class)->for($state)['number'] ?? '#'.$state)
                    ->description(fn (Payment $record): string => app(OrderNotice::class)->for($record->getOrderId())['name'] ?? '')
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make(Payment::COLUMN_REFUND_SOURCE)
                    ->label('از')
                    ->badge()
                    ->formatStateUsing(fn (?RefundSource $state): string => $state?->label() ?? '—'),
                TextColumn::make(Payment::COLUMN_AMOUNT)->label('مبلغ')->formatStateUsing(fn (int $state): string => Toman::format($state))->sortable(),
                TextColumn::make(Payment::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->state(fn (Payment $record): string => $record->statusLabel())
                    ->color(fn (Payment $record): string => match ($record->getStatus()) {
                        PaymentAttemptStatus::Pending => 'warning',
                        PaymentAttemptStatus::Paid => 'success',
                        default => 'danger',
                    }),
                TextColumn::make(Payment::COLUMN_REASON)->label('دلیل')->limit(40)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make(Payment::COLUMN_STATUS)->label('وضعیت')->options([
                    PaymentAttemptStatus::Pending->value => 'در انتظار تأیید',
                    PaymentAttemptStatus::Paid->value => 'بازگردانده شد',
                    PaymentAttemptStatus::Failed->value => 'رد شد',
                ]),
                SelectFilter::make(Payment::COLUMN_REFUND_SOURCE)->label('از')->options(RefundSource::options()),
            ])
            ->recordUrl(fn (Payment $record): string => self::getUrl('view', ['record' => $record]));
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
        return [
            'index' => ListRefunds::route('/'),
            'view' => ViewRefund::route('/{record}'),
        ];
    }
}
