<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Modules\Identity\Support\AddressText;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Models\OrderStatusLog;
use App\Modules\Orders\Support\PurchaseMargin;
use App\Modules\Payments\Queries\OrderPayments;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use App\Modules\Shipping\Actions\PackOrder;
use App\Modules\Shipping\Queries\OrderParcels;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * An order's page (roadmap S24), built only from the order's own copies (owner, 2026-11-14): the summary, the items
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
                    TextEntry::make(Order::COLUMN_SOURCING_STATUS)
                        ->label('تأمین سفارش')
                        ->badge()
                        ->state(fn (Order $record): string => $record->getSourcingStatus()?->label() ?? SourcingStatus::NONE_LABEL)
                        ->color(fn (Order $record): string => $record->getSourcingStatus()?->color() ?? 'gray')
                        ->url(fn (Order $record): ?string => $record->getPurchase() === null ? null : OrderPurchaseResource::getUrl('view', ['record' => $record->getPurchase()->getId()]))
                        ->visible(fn (Order $record): bool => $record->getStatus()->isPurchase() || $record->getPurchase() !== null),
                    TextEntry::make(Order::COLUMN_TRACKING_CODE)
                        ->label('کد رهگیری مرسوله')
                        ->copyable()
                        ->weight('bold')
                        ->placeholder('هنوز ثبت نشده')
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('payment_reference')
                        ->label('کد پیگیری پرداخت')
                        ->state(fn (Order $record): ?string => app(OrderPayments::class)->paidReference($record->getId()))
                        ->copyable()
                        ->placeholder('—')
                        ->extraAttributes(['dir' => 'ltr']),
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
                    TextEntry::make(Order::COLUMN_REFUND_ITEMS_AMOUNT)
                        ->label('بازگشت وجه کالاها')
                        ->formatStateUsing(fn (int $state): string => Toman::format($state))
                        ->visible(fn (Order $record): bool => $record->getRefundItemsAmount() !== null),
                    TextEntry::make(Order::COLUMN_REFUND_SHIPPING_AMOUNT)
                        ->label('بازگشت وجه هزینه ارسال')
                        ->formatStateUsing(fn (int $state): string => Toman::format($state))
                        ->visible(fn (Order $record): bool => $record->getRefundShippingAmount() !== null),
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
                            TableColumn::make('قیمت پایه'),
                            TableColumn::make('تخفیف حراج'),
                            TableColumn::make('تخفیف کوپن'),
                            TableColumn::make('پرداختی مشتری'),
                            TableColumn::make('تأمین‌کننده'),
                            TableColumn::make('قیمت خرید'),
                            TableColumn::make('سود ناخالص'),
                        ])
                        ->schema([
                            ImageEntry::make(OrderItem::COLUMN_IMAGE_URL)->imageSize(40)->checkFileExistence(false)->placeholder('—'),
                            TextEntry::make(OrderItem::COLUMN_PRODUCT_NAME)
                                ->helperText(fn (OrderItem $record): string => trim(($record->getVariantTitle() ?? '').' · '.$record->getSku(), ' ·')),
                            TextEntry::make(OrderItem::COLUMN_QUANTITY)->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                            TextEntry::make(OrderItem::COLUMN_LIST_PRICE)->formatStateUsing(fn (int $state): string => Toman::format($state))->helperText('هر واحد'),
                            self::perUnitAndTotal(OrderItem::COLUMN_HARAJ_DISCOUNT_UNIT, fn (OrderItem $record): int => $record->getHarajDiscountTotal()),
                            self::perUnitAndTotal(OrderItem::COLUMN_COUPON_DISCOUNT_UNIT, fn (OrderItem $record): int => $record->getCouponDiscountTotal()),
                            self::perUnitAndTotal(OrderItem::COLUMN_PAID_UNIT, fn (OrderItem $record): int => $record->getPaidTotal())->weight('bold'),
                            TextEntry::make(OrderItem::COLUMN_PROVIDER_NAME)->placeholder('قیمت درج‌شده'),
                            TextEntry::make(OrderItem::COLUMN_UNIT_COST)
                                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                                ->placeholder('—'),
                            TextEntry::make('margin')
                                ->state(fn (OrderItem $record): ?int => PurchaseMargin::itemMargin($record->getOrder(), $record) ?? self::itemMargin($record))
                                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                                ->color(fn (?int $state): ?string => $state !== null && $state < 0 ? 'danger' : null)
                                ->helperText(fn (OrderItem $record): ?string => PurchaseMargin::itemMargin($record->getOrder(), $record) !== null
                                    ? 'نهایی، با خرید '.Toman::format((int) PurchaseMargin::itemCost($record))
                                    : (self::itemMargin($record) !== null ? 'برآورد هنگام خرید مشتری' : null))
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
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : 'رایگان')
                            ->helperText(fn (Order $record): ?string => self::shippingDiscountNote($record)),
                        self::money(Order::COLUMN_TOTAL, 'مبلغ پرداختی')->weight('bold'),
                        TextEntry::make('gross_margin')
                            ->label(fn (Order $record): string => PurchaseMargin::orderMargin($record) !== null ? 'سود نهایی اقلام' : 'سود ناخالص اقلام (برآورد)')
                            ->state(fn (Order $record): ?int => PurchaseMargin::orderMargin($record) ?? self::orderMargin($record))
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                            ->color(fn (?int $state): ?string => $state !== null && $state < 0 ? 'danger' : null)
                            ->helperText(fn (Order $record): string => PurchaseMargin::orderMargin($record) !== null
                                ? 'پرداختی مشتری برای کالاها منهای هزینه سند خرید ('.Toman::format($record->getPurchase()?->getTotalCost() ?? 0).')'
                                : 'با قیمت تأمین‌کننده هنگام خرید مشتری؛ بدون اقلامی که قیمت خرید ندارند')
                            ->placeholder('—'),
                    ]),
                ]),

            self::purchaseSection(),

            self::parcelsSection(),

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
                            'tipax' => (string) ($record->getShippingQuoteSnapshot()['label'] ?? 'تیپاکس'),
                            default => (string) ($record->getShippingQuoteSnapshot()['label'] ?? 'ارسال'),
                        }.(($record->getShippingQuoteSnapshot()['is_estimate'] ?? false) ? ' (قیمت تخمینی)' : '')),
                    TextEntry::make(Order::COLUMN_SHIPPING_PRICE)
                        ->label('هزینه ارسال')
                        ->formatStateUsing(fn (int $state, Order $record): string => $record->getShippingTotal() > 0 ? Toman::format($record->getShippingTotal()) : 'رایگان')
                        ->helperText(fn (Order $record): ?string => self::shippingDiscountNote($record)),
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
     * How the paid order is packed (S31, owner 2026-11-17): each parcel's box with its sizes and weight, the items in
     * it, and the parcel's weight with the box (6 kg at most); «بسته‌بندی دوباره» repacks with today's boxes and SKUs'
     * snapshots while the order is paid or processing.
     */
    private static function parcelsSection(): Section
    {
        return Section::make('بسته‌ها')
            ->key('parcels')
            ->columnSpanFull()
            ->visible(fn (Order $record): bool => $record->getPaidAt() !== null)
            ->headerActions([
                Action::make('repack')
                    ->label('بسته‌بندی دوباره')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (Order $record): bool => in_array($record->getStatus(), [OrderStatus::Paid, OrderStatus::Processing], true)
                        && (auth('admin')->user()?->can('Update:Order') ?? false))
                    ->requiresConfirmation()
                    ->modalDescription('بسته‌ها با کارتن‌ها و ظرفیت‌های فعلی دوباره چیده می‌شوند.')
                    ->action(function (Order $record, PackOrder $packOrder): void {
                        $_ = $packOrder($record->getId());
                        Notification::make()->success()->title('بسته‌بندی دوباره انجام شد.')->send();
                    }),
            ])
            ->schema([
                TextEntry::make('parcels')
                    ->hiddenLabel()
                    ->html()
                    ->state(fn (Order $record): string => self::parcelsHtml(app(OrderParcels::class)->for($record->getId()))),
            ]);
    }

    /**
     * @param  list<array{sequence: int, box: string|null, sizes: string|null, box_weight_g: int, items: list<array{title: string, quantity: int}>, items_weight_g: int, total_weight_g: int, content: string, is_estimate: bool}>  $parcels
     */
    private static function parcelsHtml(array $parcels): string
    {
        if ($parcels === []) {
            return '<span class="text-gray-500">هنوز بسته‌بندی نشده است؛ چند لحظه بعد از پرداخت انجام می‌شود.</span>';
        }

        $grams = fn (int $grams): string => PersianDigits::toPersian(number_format($grams)).' گرم';
        $html = '<div class="space-y-3">';

        foreach ($parcels as $parcel) {
            $box = $parcel['box'] === null
                ? '<span class="text-danger-600">هیچ کارتنی جا نمی‌دهد؛ دستی بسته‌بندی کنید</span>'
                : e($parcel['box']).' ('.e(PersianDigits::toPersian((string) $parcel['sizes'])).' سانتی‌متر، '.$grams($parcel['box_weight_g']).')';
            $items = implode('، ', array_map(fn (array $item): string => e($item['title']).' × '.PersianDigits::toPersian($item['quantity']), $parcel['items']));
            $html .= '<div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">'
                .'<div class="font-bold">بسته '.PersianDigits::toPersian($parcel['sequence']).': '.$box.'</div>'
                .'<div class="mt-1 text-sm">'.$items.'</div>'
                .'<div class="mt-1 text-sm text-gray-500">وزن کالاها '.$grams($parcel['items_weight_g']).' · وزن کل '.$grams($parcel['total_weight_g']).' · '.e($parcel['content'])
                .($parcel['is_estimate'] ? ' · <span class="text-warning-600">تخمینی (وزن یا اندازه کالایی ثبت نشده)</span>' : '').'</div>'
                .'</div>';
        }

        return $html.'</div>';
    }

    /**
     * The order's purchase document (S25, owner 2026-11-16): status, edit deadline, cost, final margin and its rows,
     * read only; rows are added and received on the document's page («باز کردن سند خرید»).
     */
    private static function purchaseSection(): Section
    {
        return Section::make('سند خرید')
            ->columnSpanFull()
            ->visible(fn (Order $record): bool => $record->getStatus()->isPurchase() || $record->getPurchase() !== null)
            ->description(fn (Order $record): ?string => $record->getPurchase() === null ? 'هنوز سند خریدی ثبت نشده است؛ از دکمه «ثبت سند خرید» بالای صفحه.' : null)
            ->headerActions([
                Action::make('openPurchaseDocument')
                    ->label('باز کردن سند خرید')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->visible(fn (Order $record): bool => $record->getPurchase() !== null)
                    ->url(fn (Order $record): ?string => $record->getPurchase() === null ? null : OrderPurchaseResource::getUrl('view', ['record' => $record->getPurchase()->getId()])),
            ])
            ->schema([
                Grid::make(['default' => 2, 'md' => 5])
                    ->visible(fn (Order $record): bool => $record->getPurchase() !== null)
                    ->schema([
                        TextEntry::make('purchase_number')
                            ->label('شماره سند')
                            ->state(fn (Order $record): ?string => $record->getPurchase()?->getNumber())
                            ->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make('purchase_status')
                            ->label('وضعیت سند')
                            ->badge()
                            ->state(fn (Order $record): ?string => $record->getPurchase()?->getStatus()->label())
                            ->color(fn (Order $record): string => $record->getPurchase()?->getStatus()->color() ?? 'gray'),
                        TextEntry::make('purchase_editable_until')
                            ->label('ویرایش ردیف‌ها تا')
                            ->state(fn (Order $record): ?string => ($until = $record->getPurchase()?->getEditableUntil()) === null ? null : JalaliDate::display($until, 'j F Y، H:i'))
                            ->color(fn (Order $record): ?string => $record->getPurchase()?->isLocked() ? 'danger' : null)
                            ->placeholder('تا ۴۸ ساعت پس از ثبت سند'),
                        TextEntry::make('purchase_cost')
                            ->label('هزینه خرید')
                            ->state(fn (Order $record): int => $record->getPurchase()?->getTotalCost() ?? 0)
                            ->formatStateUsing(fn (int $state): string => Toman::format($state)),
                        TextEntry::make('purchase_margin')
                            ->label('سود نهایی')
                            ->state(fn (Order $record): ?int => PurchaseMargin::orderMargin($record))
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                            ->color(fn (?int $state): ?string => $state !== null && $state < 0 ? 'danger' : null)
                            ->weight('bold')
                            ->placeholder('پس از ثبت همه ردیف‌ها'),
                    ]),
                RepeatableEntry::make('purchase.items')
                    ->hiddenLabel()
                    ->visible(fn (Order $record): bool => $record->getPurchase()?->items->isNotEmpty() ?? false)
                    ->table([
                        TableColumn::make('کالا'),
                        TableColumn::make('تأمین‌کننده'),
                        TableColumn::make('تعداد'),
                        TableColumn::make('قیمت خرید واحد'),
                        TableColumn::make('جمع خرید'),
                        TableColumn::make('انقضا'),
                        TableColumn::make('دریافت‌شده'),
                        TableColumn::make('کسری'),
                    ])
                    ->schema([
                        TextEntry::make('product')->state(fn (OrderPurchaseItem $record): string => $record->getOrderItem()->getProductName()),
                        TextEntry::make(OrderPurchaseItem::COLUMN_PROVIDER_NAME),
                        TextEntry::make(OrderPurchaseItem::COLUMN_QUANTITY)->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                        TextEntry::make(OrderPurchaseItem::COLUMN_UNIT_COST)
                            ->formatStateUsing(fn (int $state): string => Toman::format($state))
                            ->color(fn (OrderPurchaseItem $record): ?string => $record->getUnitCost() >= $record->getOrderItem()->getPaidUnit() ? 'danger' : null),
                        TextEntry::make('row_total')->state(fn (OrderPurchaseItem $record): int => $record->getTotalCost())->formatStateUsing(fn (int $state): string => Toman::format($state)),
                        TextEntry::make(OrderPurchaseItem::COLUMN_EXPIRY_DATE)->jalaliDate()->placeholder('—'), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                        TextEntry::make(OrderPurchaseItem::COLUMN_RECEIVED_QUANTITY)
                            ->formatStateUsing(fn (int $state, OrderPurchaseItem $record): string => PersianDigits::toPersian($state).' از '.PersianDigits::toPersian($record->getQuantity())),
                        TextEntry::make(OrderPurchaseItem::COLUMN_MISSING_QUANTITY)
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? PersianDigits::toPersian($state) : '—')
                            ->helperText(fn (OrderPurchaseItem $record): ?string => $record->getMissingNote()),
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
        return $item->getUnitCost() === null ? null : $item->getPaidTotal() - $item->getUnitCost() * $item->getQuantity();
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

    /**
     * An item's amount per unit, with the item's total under it («جمع ۳ عدد: …»); «—» when there is none.
     *
     * @param  callable(OrderItem): int  $total
     */
    private static function perUnitAndTotal(string $column, callable $total): TextEntry
    {
        return TextEntry::make($column)
            ->formatStateUsing(fn (int $state): string => $state > 0 ? Toman::format($state) : '—')
            ->helperText(fn (OrderItem $record): ?string => $total($record) > 0
                ? 'جمع '.PersianDigits::toPersian($record->getQuantity()).' عدد: '.Toman::format($total($record))
                : null);
    }

    /**
     * «هزینه ارسال ۵۰٬۰۰۰ تومان، رایگان با کوپن» when shipping was discounted (owner, 2026-11-16).
     */
    private static function shippingDiscountNote(Order $record): ?string
    {
        if ($record->getShippingDiscount() === 0) {
            return null;
        }

        $reason = match ($record->getShippingDiscountReason()) {
            'coupon' => 'با کوپن ارسال رایگان',
            'threshold' => 'با رسیدن به حد ارسال رایگان',
            default => null,
        };

        return trim('قیمت ارسال '.Toman::format($record->getShippingPrice()).'، تخفیف '.Toman::format($record->getShippingDiscount()).' '.($reason ?? ''));
    }

    private static function money(string $column, string $label): TextEntry
    {
        return TextEntry::make($column)->label($label)->formatStateUsing(fn (int $state): string => Toman::format($state));
    }
}
