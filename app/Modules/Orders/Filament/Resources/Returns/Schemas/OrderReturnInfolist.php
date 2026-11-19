<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Schemas;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnItem;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use App\Modules\Shipping\Enums\TipaxParcelStage;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A return's page (S27, owner 2026-11-19): full or partial, its status, the units coming back with what the customer
 * paid for each and the refund, and Tipax's price, boxes and tracking codes for the way back.
 */
final class OrderReturnInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مرجوعی')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make('number')->label('شماره مرجوعی')->state(fn (OrderReturn $record): string => $record->getNumber())->weight('bold')->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('order_number')
                        ->label('سفارش')
                        ->state(fn (OrderReturn $record): string => (string) $record->getOrder()->getNumber())
                        ->url(fn (OrderReturn $record): string => OrderResource::getUrl('view', ['record' => $record->getOrderId()]))
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('scope')
                        ->label('نوع')
                        ->badge()
                        ->state(fn (OrderReturn $record): string => $record->getScope()->label())
                        ->color(fn (OrderReturn $record): string => $record->getScope()->color()),
                    TextEntry::make(OrderReturn::COLUMN_STATUS)
                        ->label('وضعیت')
                        ->badge()
                        ->formatStateUsing(fn (OrderReturnStatus $state): string => $state->label())
                        ->color(fn (OrderReturnStatus $state): string => $state->color()),
                    TextEntry::make('pickup')
                        ->label('بازگشت کالا')
                        ->state(fn (OrderReturn $record): string => $record->needsPickup() ? 'جمع‌آوری توسط تیپاکس' : 'ارسال نشده بود؛ بدون جمع‌آوری'),
                    TextEntry::make(OrderReturn::COLUMN_REASON)->label('دلیل')->placeholder('—')->columnSpanFull(),
                    self::date(OrderReturn::COLUMN_CREATED_AT, 'ثبت'),
                    self::date(OrderReturn::COLUMN_APPROVED_AT, 'تأیید'),
                    self::date(OrderReturn::COLUMN_CANCELLED_AT, 'لغو'),
                    self::date(OrderReturn::COLUMN_REFUNDED_AT, 'بازگشت وجه'),
                    TextEntry::make(OrderReturn::COLUMN_REFUND_REFERENCE)
                        ->label('شماره پیگیری بازگشت وجه')
                        ->copyable()
                        ->extraAttributes(['dir' => 'ltr'])
                        ->visible(fn (OrderReturn $record): bool => $record->getRefundReference() !== null),
                    TextEntry::make(OrderReturn::COLUMN_CANCEL_REASON)
                        ->label('دلیل لغو')
                        ->visible(fn (OrderReturn $record): bool => $record->getCancelReason() !== null),
                ]),
            Section::make('کالاها و مبلغ بازگشتی')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('کالا'),
                            TableColumn::make('تعداد'),
                            TableColumn::make('پرداختی هر واحد'),
                            TableColumn::make('مبلغ بازگشتی'),
                        ])
                        ->schema([
                            TextEntry::make('title')->state(fn (OrderReturnItem $record): string => trim($record->getOrderItem()->getProductName().' '.($record->getOrderItem()->getVariantTitle() ?? ''))),
                            TextEntry::make(OrderReturnItem::COLUMN_QUANTITY)
                                ->formatStateUsing(fn (int $state, OrderReturnItem $record): string => PersianDigits::toPersian($state).' از '.PersianDigits::toPersian($record->getOrderItem()->getQuantity())),
                            TextEntry::make(OrderReturnItem::COLUMN_PAID_UNIT)->formatStateUsing(fn (int $state): string => Toman::format($state)),
                            TextEntry::make(OrderReturnItem::COLUMN_REFUND_AMOUNT)->formatStateUsing(fn (int $state): string => Toman::format($state))->weight('bold'),
                        ]),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextEntry::make(OrderReturn::COLUMN_ITEMS_REFUND)->label('مبلغ کالاها')->formatStateUsing(fn (int $state): string => Toman::format($state)),
                        TextEntry::make(OrderReturn::COLUMN_SHIPPING_FEE_REFUND)
                            ->label('هزینه ارسال سفارش')
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : '—')
                            ->helperText('فقط در مرجوعی‌ای که همه کالاها را برمی‌گرداند'),
                        TextEntry::make('refund_total')
                            ->label('جمع مبلغ بازگشتی به مشتری')
                            ->state(fn (OrderReturn $record): string => Toman::format($record->getRefundTotal()))
                            ->weight('bold')
                            ->color('primary'),
                    ]),
                ]),
            Section::make('ارسال مرجوعی با تیپاکس')
                ->columnSpanFull()
                ->visible(fn (OrderReturn $record): bool => $record->needsPickup())
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make(OrderReturn::COLUMN_RETURN_SHIPPING_PRICE)
                        ->label('هزینه ارسال مرجوعی')
                        ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                        ->placeholder('تیپاکس قیمت نداد')
                        ->helperText('به عهده فروشگاه؛ از مبلغ بازگشتی کم نمی‌شود.')
                        ->weight('bold'),
                    TextEntry::make(OrderReturn::COLUMN_RETURN_SERVICE_TITLE)->label('سرویس')->placeholder('—'),
                    TextEntry::make('parcels')
                        ->label('بسته‌ها')
                        ->state(fn (OrderReturn $record): array => array_map(
                            fn (array $parcel): string => ($parcel['box'] ?? 'بدون کارتن').' · '.PersianDigits::toPersian(number_format($parcel['weight_g'] / 1000, 2)).' کیلوگرم',
                            $record->getParcels(),
                        ))
                        ->listWithLineBreaks()
                        ->placeholder('—'),
                    TextEntry::make(OrderReturn::COLUMN_TIPAX_STAGE)
                        ->label('وضعیت در تیپاکس')
                        ->badge()
                        ->formatStateUsing(fn (TipaxParcelStage $state): string => $state->label())
                        ->helperText(fn (OrderReturn $record): ?string => $record->getTipaxStatus())
                        ->placeholder('پس از تأیید'),
                    self::date(OrderReturn::COLUMN_TIPAX_CHECKED_AT, 'آخرین استعلام'),
                    self::date(OrderReturn::COLUMN_PICKED_UP_AT, 'جمع‌آوری'),
                    self::date(OrderReturn::COLUMN_RECEIVED_AT, 'تحویل انبار'),
                    TextEntry::make(OrderReturn::COLUMN_TRACKING_CODES)
                        ->label('کد رهگیری مرجوعی')
                        ->state(fn (OrderReturn $record): array => $record->getTrackingCodes())
                        ->listWithLineBreaks()
                        ->copyable()
                        ->placeholder('پس از تأیید')
                        ->extraAttributes(['dir' => 'ltr']),
                ]),
        ]);
    }

    private static function date(string $column, string $label): TextEntry
    {
        return TextEntry::make($column)
            ->label($label)
            ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : JalaliDate::display($state, 'j F Y، H:i'))
            ->placeholder('—');
    }
}
