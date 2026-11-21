<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\Refunds\Schemas;

use App\Modules\Identity\Queries\AdminNames;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Queries\OrderNotice;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Queries\OrderPayments;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A refund's page (owner, 2026-11-19): what is owed and why, where it came from with links to its order and return,
 * the customer and the card they paid with, the order's money so far, and who opened, approved or rejected it.
 */
final class RefundInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بازگشت وجه')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make(Payment::COLUMN_AMOUNT)->label('مبلغ')->formatStateUsing(fn (int $state): string => Toman::format($state))->weight('bold'),
                    TextEntry::make(Payment::COLUMN_STATUS)
                        ->label('وضعیت')
                        ->badge()
                        ->state(fn (Payment $record): string => $record->statusLabel())
                        ->color(fn (Payment $record): string => match ($record->getStatus()) {
                            PaymentAttemptStatus::Pending => 'warning',
                            PaymentAttemptStatus::Paid => 'success',
                            default => 'danger',
                        }),
                    TextEntry::make(Payment::COLUMN_REFUND_SOURCE)->label('از')->formatStateUsing(fn (?RefundSource $state): string => $state?->label() ?? '—'),
                    TextEntry::make(Payment::COLUMN_REASON)->label('دلیل')->placeholder('—'),
                    TextEntry::make('split')
                        ->label('کالاها / هزینه ارسال')
                        ->state(fn (Payment $record): ?string => $record->getDetails() === [] ? null
                            : Toman::format((int) ($record->getDetails()['items'] ?? 0)).' / '.Toman::format((int) ($record->getDetails()['shipping'] ?? 0)))
                        ->visible(fn (Payment $record): bool => $record->getDetails() !== []),
                    TextEntry::make(Payment::COLUMN_CREATED_AT)
                        ->label('ثبت')
                        ->formatStateUsing(fn (mixed $state): string => JalaliDate::display($state, 'j F Y، H:i'))
                        ->helperText(fn (Payment $record): string => $record->getCreatedBy() === null ? 'خودکار' : 'توسط '.(app(AdminNames::class)->for([$record->getCreatedBy()])[$record->getCreatedBy()] ?? 'مدیر')),
                ]),
            Section::make('سفارش و مشتری')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make('order')
                        ->label('سفارش')
                        ->state(fn (Payment $record): string => app(OrderNotice::class)->for($record->getOrderId())['number'] ?? '#'.$record->getOrderId())
                        ->url(fn (Payment $record): string => OrderResource::getUrl('view', ['record' => $record->getOrderId()]))
                        ->color('primary')
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('order_return')
                        ->label('مرجوعی')
                        ->state(fn (Payment $record): ?string => $record->getOrderReturnId() === null ? null : app(OrderReturns::class)->numberOf($record->getOrderReturnId()))
                        ->url(fn (Payment $record): ?string => $record->getOrderReturnId() === null ? null : OrderReturnResource::getUrl('view', ['record' => $record->getOrderReturnId()]))
                        ->color('primary')
                        ->extraAttributes(['dir' => 'ltr'])
                        ->visible(fn (Payment $record): bool => $record->getOrderReturnId() !== null),
                    TextEntry::make('customer')
                        ->label('مشتری')
                        ->state(fn (Payment $record): string => app(OrderNotice::class)->for($record->getOrderId())['name'] ?? '—')
                        ->helperText(fn (Payment $record): string => PersianDigits::toPersian(app(OrderNotice::class)->for($record->getOrderId())['mobile'] ?? '')),
                    TextEntry::make('card')
                        ->label('کارت پرداخت سفارش')
                        ->state(fn (Payment $record): ?string => app(OrderPayments::class)->paidCard($record->getOrderId()))
                        ->formatStateUsing(fn (string $state): string => PersianDigits::toPersian($state))
                        ->placeholder('—')
                        ->helperText('بازگشت وجه به همین کارت واریز شود.')
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('balance')
                        ->label('حساب سفارش')
                        ->state(function (Payment $record): string {
                            $balance = app(OrderPayments::class)->balance($record->getOrderId());

                            return 'پرداخت: '.Toman::format($balance['paid']).' · بازگشت داده‌شده: '.Toman::format($balance['refunded']).' · در انتظار تأیید: '.Toman::format($balance['refund_waiting']);
                        })
                        ->columnSpan(['md' => 2, 'xl' => 4]),
                ]),
            Section::make('تأیید یا رد')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->visible(fn (Payment $record): bool => $record->getDecidedAt() !== null)
                ->schema([
                    TextEntry::make(Payment::COLUMN_REF_ID)->label('شماره پیگیری واریز')->copyable()->placeholder('—')->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make(Payment::COLUMN_FAILURE_REASON)->label('دلیل رد')->placeholder('—'),
                    TextEntry::make(Payment::COLUMN_DECIDED_AT)
                        ->label('زمان')
                        ->formatStateUsing(fn (mixed $state): string => JalaliDate::display($state, 'j F Y، H:i'))
                        ->helperText(fn (Payment $record): ?string => $record->getDecidedBy() === null ? null : 'توسط '.(app(AdminNames::class)->for([$record->getDecidedBy()])[$record->getDecidedBy()] ?? 'مدیر')),
                ]),
        ]);
    }
}
