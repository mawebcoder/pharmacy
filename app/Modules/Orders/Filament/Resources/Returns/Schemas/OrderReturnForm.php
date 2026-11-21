<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Schemas;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\ReturnReason;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * «ثبت مرجوعی» (S27, owner 2026-11-19): pick a paid order not sent yet or a delivered one (a cancelled order is refunded
 * on the order), then rows of item, units and reason. An item may be on several rows with different reasons; together
 * they may not pass what no open return holds.
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
                            ->whereIn(Order::COLUMN_STATUS, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Delivered])
                            ->where(Order::COLUMN_NUMBER, 'like', '%'.trim($search).'%')
                            ->orderByDesc(Order::COLUMN_ID)
                            ->limit(20)
                            ->pluck(Order::COLUMN_NUMBER, Order::COLUMN_ID)
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? Order::query()->find((int) $value)?->getNumber() : null)
                        ->helperText('سفارش پرداخت‌شده‌ای که هنوز ارسال نشده (بدون جمع‌آوری، بازگشت وجه پس از تأیید) یا سفارش تحویل‌شده. سفارش لغوشده از صفحه خودش بازگشت وجه می‌گیرد.')
                        ->afterStateUpdated(fn (Set $set) => $set('items', [])),
                    Textarea::make('reason')->label('توضیح کلی')->maxLength(2000)->rows(2),
                ]),
            Section::make('کالاهای مرجوعی')
                ->description('هر ردیف: کالا، تعداد و دلیل. یک کالا می‌تواند با دلیل‌های مختلف در چند ردیف بیاید.')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => filled($get('order_id')))
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->addActionLabel('افزودن ردیف')
                        ->reorderable(false)
                        ->minItems(1)
                        ->columns(['default' => 1, 'md' => 6])
                        ->schema([
                            Select::make('order_item_id')
                                ->label('کالا')
                                ->required()
                                ->options(fn (Get $get): array => self::itemOptions($get('../../order_id')))
                                ->columnSpan(['md' => 2]),
                            TextInput::make('quantity')
                                ->label('تعداد')
                                ->numeric()
                                ->integer()
                                ->required()
                                ->minValue(1)
                                ->rule(fn (Get $get): Closure => self::withinRemaining($get)),
                            Select::make('reason')
                                ->label('دلیل')
                                ->required()
                                ->options(ReturnReason::options())
                                ->default(ReturnReason::Damaged->value),
                            TextInput::make('note')->label('توضیح')->maxLength(500)->columnSpan(['md' => 2]),
                        ]),
                ]),
        ]);
    }

    /**
     * The order's items with units left, with what is left and was paid per unit.
     *
     * @return array<int, string>
     */
    private static function itemOptions(mixed $orderId): array
    {
        $order = is_numeric($orderId) ? Order::query()->with('items')->find((int) $orderId) : null;

        if (! $order instanceof Order) {
            return [];
        }

        $remaining = app(OrderReturns::class)->remaining($order);
        $options = [];

        foreach ($order->items as $item) {
            if (($remaining[$item->getId()] ?? 0) > 0) {
                $options[$item->getId()] = trim($item->getProductName().' '.($item->getVariantTitle() ?? ''))
                    .' — قابل مرجوع: '.PersianDigits::toPersian((string) $remaining[$item->getId()])
                    .' · هر واحد '.Toman::format($item->getPaidUnit());
            }
        }

        return $options;
    }

    /**
     * The rows of one item together may not pass the units no open return holds.
     */
    private static function withinRemaining(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $orderItemId = (int) $get('order_item_id');
            $order = is_numeric($get('../../order_id')) ? Order::query()->with('items')->find((int) $get('../../order_id')) : null;

            if (! $order instanceof Order || $orderItemId === 0) {
                return;
            }

            $total = 0;

            foreach ((array) $get('../../items') as $row) {
                if ((int) ($row['order_item_id'] ?? 0) === $orderItemId) {
                    $total += (int) ($row['quantity'] ?? 0);
                }
            }

            $left = app(OrderReturns::class)->remaining($order)[$orderItemId] ?? 0;

            if ($total > $left) {
                $fail('جمع ردیف‌های این کالا از '.PersianDigits::toPersian((string) $left).' عدد قابل مرجوع بیشتر است.');
            }
        };
    }
}
