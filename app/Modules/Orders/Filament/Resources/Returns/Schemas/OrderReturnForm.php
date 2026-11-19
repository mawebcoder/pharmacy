<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Schemas;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * «ثبت مرجوعی» (S27, owner 2026-11-19): pick a paid order not sent yet, a delivered one, or a cancelled one that went
 * out, then how many units of each item come back (at most what no open return holds). A cancelled order starts with every unit, a delivered one
 * with none.
 */
final class OrderReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('سفارش')
                ->columnSpanFull()
                ->schema([
                    Select::make('order_id')
                        ->label('سفارش')
                        ->required()
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(fn (string $search): array => Order::query()
                            ->whereIn(Order::COLUMN_STATUS, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Delivered, OrderStatus::Cancelled])
                            ->where(Order::COLUMN_NUMBER, 'like', '%'.trim($search).'%')
                            ->orderByDesc(Order::COLUMN_ID)
                            ->limit(20)
                            ->pluck(Order::COLUMN_NUMBER, Order::COLUMN_ID)
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? Order::query()->find((int) $value)?->getNumber() : null)
                        ->helperText('سفارش پرداخت‌شده‌ای که هنوز ارسال نشده (بدون جمع‌آوری، بازگشت وجه پس از تأیید)، سفارش تحویل‌شده، یا لغوشده‌ای که ارسال شده بود.')
                        ->afterStateUpdated(fn (Set $set, mixed $state) => $set('items', is_numeric($state) ? self::itemRows((int) $state) : [])),
                    Textarea::make('reason')->label('دلیل مرجوعی')->maxLength(2000)->rows(3),
                ]),
            Section::make('کالاهای مرجوعی')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => filled($get('order_id')))
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(['default' => 1, 'md' => 4])
                        ->schema([
                            Hidden::make('order_item_id'),
                            TextInput::make('title')->label('کالا')->disabled()->dehydrated(false)->columnSpan(['md' => 2]),
                            TextInput::make('paid_unit')->label('پرداختی هر واحد')->disabled()->dehydrated(false),
                            TextInput::make('quantity')
                                ->label('تعداد مرجوعی')
                                ->numeric()
                                ->integer()
                                ->required()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): int => (int) $get('remaining'))
                                ->helperText(fn (Get $get): string => 'حداکثر '.PersianDigits::toPersian((string) (int) $get('remaining'))),
                            Hidden::make('remaining')->dehydrated(false),
                        ]),
                ]),
        ]);
    }

    /**
     * One row per item of the order with units left to return.
     *
     * @return list<array{order_item_id: int, title: string, paid_unit: string, remaining: int, quantity: int}>
     */
    public static function itemRows(int $orderId): array
    {
        $order = Order::query()->with('items')->find($orderId);

        if (! $order instanceof Order) {
            return [];
        }

        $remaining = app(OrderReturns::class)->remaining($order);
        $rows = [];

        foreach ($order->items as $item) {
            $left = $remaining[$item->getId()] ?? 0;

            if ($left > 0) {
                $rows[] = [
                    'order_item_id' => $item->getId(),
                    'title' => trim($item->getProductName().' '.($item->getVariantTitle() ?? '')),
                    'paid_unit' => Toman::format($item->getPaidUnit()),
                    'remaining' => $left,
                    'quantity' => $order->getStatus() === OrderStatus::Cancelled ? $left : 0,
                ];
            }
        }

        return $rows;
    }
}
