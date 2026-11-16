<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Modules\Identity\Support\AddressText;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusLog;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * An order's page (roadmap S29), built only from the order's own copies (owner, 2026-11-14): the summary, the items
 * with what we paid the supplier and the margin, the totals, the customer and address snapshots, and the status
 * history. Never from the live product, price, coupon, address or customer.
 */
final class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('خلاصه سفارش')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make(Order::COLUMN_NUMBER)->label('شماره سفارش')->copyable()->weight('bold')->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make(Order::COLUMN_STATUS)
                        ->label('وضعیت')
                        ->badge()
                        ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                        ->color(fn (OrderStatus $state): string => $state->color()),
                    TextEntry::make(Order::COLUMN_PAYMENT_STATUS)
                        ->label('پرداخت')
                        ->badge()
                        ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                        ->color(fn (PaymentStatus $state): string => $state->color()),
                    TextEntry::make(Order::COLUMN_TRACKING_CODE)->label('کد رهگیری')->copyable()->placeholder('—')->extraAttributes(['dir' => 'ltr']),
                    self::date(Order::COLUMN_PAID_AT, 'پرداخت'),
                    self::date(Order::COLUMN_PLACED_AT, 'ثبت'),
                    self::date(Order::COLUMN_DELIVERED_AT, 'تحویل'),
                    self::date(Order::COLUMN_CANCELLED_AT, 'لغو'),
                    self::date(Order::COLUMN_EXPIRES_AT, 'مهلت پرداخت')->visible(fn (Order $record): bool => $record->getStatus() === OrderStatus::Pending),
                    self::date(Order::COLUMN_REFUNDED_AT, 'بازگشت وجه')->visible(fn (Order $record): bool => $record->getRefundedAt() !== null),
                    TextEntry::make(Order::COLUMN_REFUND_REFERENCE)
                        ->label('شماره پیگیری بازگشت وجه')
                        ->copyable()
                        ->extraAttributes(['dir' => 'ltr'])
                        ->visible(fn (Order $record): bool => $record->getRefundReference() !== null),
                ]),

            Section::make('اقلام')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('')->width('56px'),
                            TableColumn::make('کالا'),
                            TableColumn::make('تعداد'),
                            TableColumn::make('قیمت واحد'),
                            TableColumn::make('تخفیف'),
                            TableColumn::make('جمع'),
                            TableColumn::make('تأمین‌کننده'),
                            TableColumn::make('قیمت خرید'),
                            TableColumn::make('سود ناخالص'),
                        ])
                        ->schema([
                            ImageEntry::make(OrderItem::COLUMN_IMAGE_URL)->imageSize(40)->checkFileExistence(false)->placeholder('—'),
                            TextEntry::make(OrderItem::COLUMN_PRODUCT_NAME)
                                ->helperText(fn (OrderItem $record): string => trim(($record->getVariantTitle() ?? '').' · '.$record->getSku(), ' ·')),
                            TextEntry::make(OrderItem::COLUMN_QUANTITY)->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                            TextEntry::make(OrderItem::COLUMN_UNIT_PRICE)->formatStateUsing(fn (int $state): string => Toman::format($state)),
                            TextEntry::make(OrderItem::COLUMN_DISCOUNT)->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : '—'),
                            TextEntry::make(OrderItem::COLUMN_TOTAL)->weight('bold')->formatStateUsing(fn (int $state): string => Toman::format($state)),
                            TextEntry::make(OrderItem::COLUMN_PROVIDER_NAME)->placeholder('قیمت درج‌شده'),
                            TextEntry::make(OrderItem::COLUMN_UNIT_COST)
                                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                                ->placeholder('—'),
                            TextEntry::make('margin')
                                ->state(fn (OrderItem $record): ?int => self::itemMargin($record))
                                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                                ->color(fn (?int $state): ?string => $state !== null && $state < 0 ? 'danger' : null)
                                ->placeholder('—'),
                        ]),
                    Grid::make(['default' => 2, 'md' => 5])->schema([
                        self::money(Order::COLUMN_SUBTOTAL, 'جمع کالاها'),
                        TextEntry::make(Order::COLUMN_DISCOUNT_TOTAL)
                            ->label('تخفیف')
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : '—')
                            ->helperText(fn (Order $record): ?string => $record->getCouponCode() !== null ? 'کد تخفیف '.$record->getCouponCode() : null),
                        TextEntry::make(Order::COLUMN_SHIPPING_TOTAL)
                            ->label('هزینه ارسال')
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : 'رایگان'),
                        self::money(Order::COLUMN_TOTAL, 'مبلغ پرداختی')->weight('bold'),
                        TextEntry::make('gross_margin')
                            ->label('سود ناخالص اقلام')
                            ->state(fn (Order $record): ?int => self::orderMargin($record))
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                            ->helperText('بدون اقلامی که قیمت خرید ندارند')
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('مشتری و ارسال')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('customer')
                        ->label('مشتری')
                        ->state(fn (Order $record): string => trim(($record->getCustomerSnapshot()['name'] ?? '').' · '.PersianDigits::toPersian((string) ($record->getCustomerSnapshot()['mobile'] ?? '')), ' ·') ?: '—'),
                    TextEntry::make('receiver')
                        ->label('گیرنده')
                        ->state(fn (Order $record): string => trim(($record->getAddressSnapshot()['receiver_name'] ?? '').' · '.PersianDigits::toPersian((string) ($record->getAddressSnapshot()['receiver_mobile'] ?? '')), ' ·') ?: '—'),
                    TextEntry::make('address')
                        ->label('نشانی')
                        ->state(fn (Order $record): string => AddressText::from($record->getAddressSnapshot())->line())
                        ->columnSpanFull(),
                    TextEntry::make('postal_code')
                        ->label('کد پستی')
                        ->state(fn (Order $record): string => PersianDigits::toPersian((string) ($record->getAddressSnapshot()['postal_code'] ?? '')) ?: '—'),
                    TextEntry::make(Order::COLUMN_SHIPPING_METHOD)
                        ->label('روش ارسال')
                        ->formatStateUsing(fn (string $state, Order $record): string => match ($state) {
                            'own_courier' => 'پیک فروشگاه',
                            'tipax' => 'تیپاکس',
                            default => (string) ($record->getShippingQuoteSnapshot()['label'] ?? 'ارسال'),
                        }),
                    TextEntry::make('shipping_price')
                        ->label('هزینه ارسال')
                        ->state(fn (Order $record): int => $record->getShippingTotal())
                        ->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : 'رایگان'),
                    TextEntry::make(Order::COLUMN_CUSTOMER_NOTE)->label('توضیح مشتری')->placeholder('—')->columnSpanFull(),
                    TextEntry::make(Order::COLUMN_ADMIN_NOTE)->label('یادداشت مدیر')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('تاریخچه وضعیت')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('statusLogs')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('زمان'),
                            TableColumn::make('تغییر'),
                            TableColumn::make('توسط'),
                            TableColumn::make('توضیح'),
                        ])
                        ->schema([
                            TextEntry::make(OrderStatusLog::COLUMN_CREATED_AT)->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                            TextEntry::make(OrderStatusLog::COLUMN_TO_STATUS)
                                ->state(fn (OrderStatusLog $record): string => ($record->getFromStatus()?->label() ?? 'ثبت سفارش').' ← '.$record->getToStatus()->label()),
                            TextEntry::make(OrderStatusLog::COLUMN_ACTOR_TYPE)
                                ->state(fn (OrderStatusLog $record): string => $record->getActorType()->label().($record->getActorId() !== null ? ' #'.PersianDigits::toPersian($record->getActorId()) : '')),
                            TextEntry::make(OrderStatusLog::COLUMN_NOTE)->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    /**
     * What the item earned the store before the gateway fee and shipping: what the customer paid for it (after its
     * share of the coupon) minus what we pay the supplier. Null when it was sold at the printed price without a
     * supplier cost.
     */
    public static function itemMargin(OrderItem $item): ?int
    {
        return $item->getUnitCost() === null ? null : $item->getTotal() - $item->getUnitCost() * $item->getQuantity();
    }

    public static function orderMargin(Order $order): ?int
    {
        $margins = $order->items->map(fn (OrderItem $item): ?int => self::itemMargin($item))->filter(fn (?int $margin): bool => $margin !== null);

        return $margins->isEmpty() ? null : (int) $margins->sum();
    }

    private static function date(string $column, string $label): TextEntry
    {
        return TextEntry::make($column)->label($label)->jalaliDateTime()->placeholder('—'); // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
    }

    private static function money(string $column, string $label): TextEntry
    {
        return TextEntry::make($column)->label($label)->formatStateUsing(fn (int $state): string => Toman::format($state));
    }
}
