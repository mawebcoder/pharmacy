<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Purchases\Schemas;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Support\PurchaseMargin;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\Toman;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A purchase document's page (S25): its order (linked), status, the order's sourcing status, what the customer paid
 * for the items, what the rows cost and the final margin. The rows are the relation manager below.
 */
final class OrderPurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('سند خرید')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make('order_number')
                        ->label('سفارش')
                        ->state(fn (OrderPurchase $record): string => (string) $record->getOrder()->getNumber())
                        ->url(fn (OrderPurchase $record): string => OrderResource::getUrl('view', ['record' => $record->getOrderId()]))
                        ->color('primary')
                        ->weight('bold')
                        ->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('order_status')
                        ->label('وضعیت سفارش')
                        ->badge()
                        ->state(fn (OrderPurchase $record): OrderStatus => $record->getOrder()->getStatus())
                        ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                        ->color(fn (OrderStatus $state): string => $state->color()),
                    TextEntry::make(OrderPurchase::COLUMN_STATUS)
                        ->label('وضعیت سند')
                        ->badge()
                        ->formatStateUsing(fn (PurchaseStatus $state): string => $state->label())
                        ->color(fn (PurchaseStatus $state): string => $state->color())
                        ->helperText(fn (OrderPurchase $record): ?string => $record->getStatus() === PurchaseStatus::Draft ? 'تا وقتی همه تعداد همه کالاها ردیف خرید ندارند پیش‌نویس می‌ماند.' : null),
                    TextEntry::make('sourcing')
                        ->label('تأمین سفارش')
                        ->badge()
                        ->state(fn (OrderPurchase $record): string => $record->getOrder()->getSourcingStatus()?->label() ?? SourcingStatus::NONE_LABEL)
                        ->color(fn (OrderPurchase $record): string => $record->getOrder()->getSourcingStatus()?->color() ?? 'gray'),
                    TextEntry::make('items_paid')
                        ->label('پرداختی مشتری برای کالاها')
                        ->state(fn (OrderPurchase $record): int => $record->getOrder()->getItemsPaidTotal())
                        ->formatStateUsing(fn (int $state): string => Toman::format($state)),
                    TextEntry::make(OrderPurchase::COLUMN_TOTAL_COST)
                        ->label('هزینه خرید')
                        ->formatStateUsing(fn (int $state): string => Toman::format($state)),
                    TextEntry::make('final_margin')
                        ->label('سود نهایی')
                        ->state(fn (OrderPurchase $record): ?int => PurchaseMargin::orderMargin($record->getOrder()))
                        ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Toman::format($state))
                        ->color(fn (?int $state): string => $state !== null && $state < 0 ? 'danger' : 'success')
                        ->weight('bold')
                        ->placeholder('پس از ثبت همه ردیف‌ها')
                        ->helperText('پرداختی مشتری برای کالاها منهای هزینه ردیف‌های خرید'),
                    TextEntry::make('editable_until')
                        ->label('ویرایش ردیف‌ها تا')
                        ->state(fn (OrderPurchase $record): ?string => $record->getEditableUntil() === null ? null : JalaliDate::display($record->getEditableUntil(), 'j F Y، H:i'))
                        ->placeholder('تا ۴۸ ساعت پس از ثبت سند')
                        ->helperText(fn (OrderPurchase $record): ?string => $record->isLocked() ? 'مهلت ویرایش تمام شده؛ فقط دریافت و کسری ثبت می‌شود.' : null)
                        ->color(fn (OrderPurchase $record): ?string => $record->isLocked() ? 'danger' : null),
                    TextEntry::make(OrderPurchase::COLUMN_NOTE)->label('یادداشت')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }
}
