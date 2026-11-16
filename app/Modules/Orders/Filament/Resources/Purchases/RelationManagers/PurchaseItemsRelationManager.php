<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Purchases\RelationManagers;

use App\Modules\Orders\Actions\DeletePurchaseItem;
use App\Modules\Orders\Actions\ReceivePurchaseItem;
use App\Modules\Orders\Actions\SavePurchaseItem;
use App\Modules\Orders\Data\PurchaseItemData;
use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Pricing\Queries\SupplierOptions;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A purchase document's rows (roadmap S30, owner 2026-11-16), typed by the admin: for an order item, the supplier, how
 * many units and the unit cost really paid (the supplier's current price is only shown as a hint). An item may be
 * split over several suppliers; its rows never exceed what was ordered. Each row records what arrived and what is
 * missing. Every change goes through an Orders action.
 */
final class PurchaseItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'ردیف‌های خرید';

    protected static ?string $modelLabel = 'ردیف خرید';

    protected static ?string $pluralModelLabel = 'ردیف‌های خرید';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('orderItem'))
            ->paginated(false)
            ->columns([
                TextColumn::make('orderItem.'.OrderItem::COLUMN_PRODUCT_NAME)
                    ->label('کالا')
                    ->description(fn (OrderPurchaseItem $record): string => trim(($record->getOrderItem()->getVariantTitle() ?? '').' · '.$record->getOrderItem()->getSku(), ' ·')),
                TextColumn::make(OrderPurchaseItem::COLUMN_PROVIDER_NAME)->label('تأمین‌کننده'),
                TextColumn::make(OrderPurchaseItem::COLUMN_QUANTITY)->label('تعداد')->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                TextColumn::make(OrderPurchaseItem::COLUMN_UNIT_COST)
                    ->label('قیمت خرید واحد')
                    ->formatStateUsing(fn (int $state): string => Toman::format($state))
                    ->color(fn (OrderPurchaseItem $record): ?string => self::isAtLoss($record->getOrderItem(), $record->getUnitCost()) ? 'danger' : null)
                    ->description(fn (OrderPurchaseItem $record): ?string => self::isAtLoss($record->getOrderItem(), $record->getUnitCost())
                        ? 'برابر یا بیشتر از پرداختی مشتری برای هر واحد ('.Toman::format($record->getOrderItem()->getPaidUnit()).')'
                        : null),
                TextColumn::make(OrderPurchaseItem::COLUMN_EXPIRY_DATE)
                    ->label('انقضا')
                    ->jalaliDate() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->placeholder('—'),
                TextColumn::make('row_total')
                    ->label('جمع خرید')
                    ->state(fn (OrderPurchaseItem $record): int => $record->getTotalCost())
                    ->formatStateUsing(fn (int $state): string => Toman::format($state))
                    ->weight('bold'),
                TextColumn::make(OrderPurchaseItem::COLUMN_RECEIVED_QUANTITY)
                    ->label('دریافت‌شده')
                    ->formatStateUsing(fn (int $state, OrderPurchaseItem $record): string => PersianDigits::toPersian($state).' از '.PersianDigits::toPersian($record->getQuantity()))
                    ->color(fn (OrderPurchaseItem $record): string => $record->getReceivedQuantity() === $record->getQuantity() ? 'success' : 'warning'),
                TextColumn::make(OrderPurchaseItem::COLUMN_MISSING_QUANTITY)
                    ->label('کسری')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? PersianDigits::toPersian($state) : '—')
                    ->description(fn (OrderPurchaseItem $record): ?string => $record->getMissingNote())
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
            ])
            ->headerActions([
                Action::make('addRow')
                    ->label('افزودن ردیف خرید')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $this->mayChangeRows() && $this->itemOptions(null) !== [])
                    ->modalHeading('افزودن ردیف خرید')
                    ->schema($this->rowForm())
                    ->action(fn (array $data) => $this->attempt(fn () => $this->warnIfAtLoss(app(SavePurchaseItem::class)($this->purchase(), $this->rowData($data))), 'ردیف خرید اضافه شد.')),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('ثبت دریافت')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->visible(fn (): bool => $this->mayUpdate())
                    ->fillForm(fn (OrderPurchaseItem $record): array => [
                        'received' => $record->getReceivedQuantity() + $record->getOpenQuantity(),
                        'missing' => $record->getMissingQuantity(),
                        'missing_note' => $record->getMissingNote(),
                    ])
                    ->schema([
                        TextInput::make('received')->label('تعداد دریافت‌شده')->integer()->minValue(0)->required(),
                        TextInput::make('missing')->label('تعداد کسری')->integer()->minValue(0)->default(0)->required(),
                        TextInput::make('missing_note')->label('توضیح کسری (منتظر می‌مانیم، جایگزین یا بازگشت وجه)')->maxLength(500),
                    ])
                    ->action(fn (array $data, OrderPurchaseItem $record) => $this->attempt(
                        fn () => app(ReceivePurchaseItem::class)($record, (int) $data['received'], (int) ($data['missing'] ?? 0), $data['missing_note'] ?? null),
                        'دریافت ثبت شد.',
                    )),
                Action::make('editRow')
                    ->label('ویرایش')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->visible(fn (): bool => $this->mayChangeRows())
                    ->fillForm(fn (OrderPurchaseItem $record): array => [
                        'order_item_id' => $record->getOrderItemId(),
                        'provider_id' => $record->getProviderId(),
                        'quantity' => $record->getQuantity(),
                        'unit_cost' => $record->getUnitCost(),
                        'expiry_date' => $record->getExpiryDate()?->toDateString(),
                    ])
                    ->schema(fn (OrderPurchaseItem $record): array => $this->rowForm($record))
                    ->action(fn (array $data, OrderPurchaseItem $record) => $this->attempt(fn () => $this->warnIfAtLoss(app(SavePurchaseItem::class)($this->purchase(), $this->rowData($data), $record)), 'ردیف خرید ذخیره شد.')),
                Action::make('deleteRow')
                    ->label('حذف')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OrderPurchaseItem $record): bool => $this->mayChangeRows() && $record->getReceivedQuantity() === 0 && $record->getMissingQuantity() === 0)
                    ->action(fn (OrderPurchaseItem $record) => $this->attempt(fn () => app(DeletePurchaseItem::class)($record), 'ردیف خرید حذف شد.')),
            ]);
    }

    /**
     * Order item (with how many units still have no row), supplier, quantity and unit cost in toman. The supplier's
     * current price shows under the cost as a hint only.
     *
     * @return list<Select|TextInput|DatePicker|Callout>
     */
    private function rowForm(?OrderPurchaseItem $editing = null): array
    {
        $noItem = fn (Get $get): bool => blank($get('order_item_id'));

        return [
            Select::make('order_item_id')
                ->label('کالای سفارش')
                ->options(fn (): array => $this->itemOptions($editing))
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state) use ($editing): void {
                    $set('customer_paid', $this->customerPaid($state));
                    $set('quantity', blank($state) ? null : $this->unitsLeft((int) $state, $editing));
                }),
            TextInput::make('customer_paid')
                ->label('پرداختی مشتری (پس از حراج و کوپن)')
                ->disabled()
                ->dehydrated(false)
                ->placeholder('اول کالای سفارش را انتخاب کنید')
                ->formatStateUsing(fn (Get $get): ?string => $this->customerPaid($get('order_item_id'))),
            Select::make('provider_id')
                ->label('تأمین‌کننده')
                ->options(fn (): array => app(SupplierOptions::class)->names())
                ->searchable()
                ->required()
                ->disabled($noItem)
                ->live(),
            TextInput::make('quantity')
                ->label('تعداد')
                ->disabled($noItem)
                ->integer()
                ->minValue(1)
                ->maxValue(fn (Get $get): ?int => blank($get('order_item_id')) ? null : $this->unitsLeft((int) $get('order_item_id'), $editing))
                ->helperText(fn (Get $get): ?string => blank($get('order_item_id')) ? null : 'حداکثر '.PersianDigits::toPersian($this->unitsLeft((int) $get('order_item_id'), $editing)).' عدد (تعداد سفارش منهای ردیف‌های دیگر این کالا)')
                ->required(),
            TextInput::make('unit_cost')
                ->label('قیمت خرید واحد (آنچه واقعاً پرداخت شد)')
                ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                ->required()
                ->disabled($noItem)
                ->live(onBlur: true)
                ->helperText(fn (Get $get): ?string => $this->costHint($get('order_item_id'), $get('provider_id'))),
            DatePicker::make('expiry_date')
                ->label('تاریخ انقضای کالای خریداری‌شده')
                ->jalali() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                ->disabled($noItem)
                ->helperText('فقط برای ثبت؛ تاریخ دقیق انقضا به مشتری تضمین نمی‌شود.'),
            Callout::make('بدون سود')
                ->key('loss_warning')
                ->warning()
                ->description(fn (Get $get): string => 'قیمت خرید برابر یا بیشتر از پرداختی مشتری برای هر واحد ('.$this->paidUnitText($get('order_item_id')).') است؛ این کالا سودی ندارد.')
                ->visible(fn (Get $get): bool => $this->typedAtLoss($get('order_item_id'), $get('unit_cost'))),
        ];
    }

    /**
     * The order items that still have units without a row, with what is left and what earlier rows already cover:
     * «کرم اوره — ۱ از ۳ عدد باقی‌مانده (۲ عدد ثبت شده)». Fully covered items are left out, except the item of the row
     * being edited.
     *
     * @return array<int, string> order item id => label
     */
    private function itemOptions(?OrderPurchaseItem $editing): array
    {
        $options = [];

        foreach ($this->purchase()->load('order.items')->getOrder()->items as $item) {
            /** @var OrderItem $item */
            $left = $this->unitsLeft($item->getId(), $editing);

            if ($left === 0 && $editing?->getOrderItemId() !== $item->getId()) {
                continue;
            }

            $covered = $item->getQuantity() - $left;
            $options[$item->getId()] = trim($item->getProductName().' '.($item->getVariantTitle() ?? ''))
                .' — '.PersianDigits::toPersian($left).' از '.PersianDigits::toPersian($item->getQuantity()).' عدد باقی‌مانده'
                .($covered > 0 ? ' ('.PersianDigits::toPersian($covered).' عدد ثبت شده)' : '');
        }

        return $options;
    }

    /**
     * The item's ordered quantity less its other rows: the most this row may hold (SavePurchaseItem checks it too).
     */
    private function unitsLeft(int $orderItemId, ?OrderPurchaseItem $editing): int
    {
        $purchase = $this->purchase()->load(['order.items', 'items']);
        $item = $purchase->getOrder()->items->first(fn (OrderItem $item): bool => $item->getId() === $orderItemId);
        $covered = (int) $purchase->items
            ->filter(fn (OrderPurchaseItem $row): bool => $row->getOrderItemId() === $orderItemId && $row->getId() !== $editing?->getId())
            ->sum(fn (OrderPurchaseItem $row): int => $row->getQuantity());

        return $item instanceof OrderItem ? max(0, $item->getQuantity() - $covered) : 0;
    }

    /**
     * What the customer paid for one unit, and the supplier's current price as a hint only.
     */
    private function costHint(mixed $orderItemId, mixed $providerId): ?string
    {
        $item = $this->orderItem($orderItemId);

        if (! $item instanceof OrderItem) {
            return null;
        }

        $lines = ['پرداختی مشتری برای هر واحد: '.Toman::format($item->getPaidUnit())];

        if (! blank($providerId) && $item->getVariantId() !== null) {
            $cost = app(SupplierOptions::class)->cost((int) $providerId, $item->getVariantId());
            $lines[] = $cost === null ? 'این تأمین‌کننده قیمتی برای این کالا ثبت نکرده است.' : 'قیمت فعلی این تأمین‌کننده: '.Toman::format($cost).' (فقط راهنما)';
        }

        return implode(' · ', $lines);
    }

    /**
     * The typed unit cost (toman) is at or above what the customer paid for one unit (owner, 2026-11-16): the form
     * shows a warning box; saving is still allowed.
     */
    private function typedAtLoss(mixed $orderItemId, mixed $typedToman): bool
    {
        $item = $this->orderItem($orderItemId);
        $typed = Toman::rialFromInput(is_scalar($typedToman) ? (string) $typedToman : null);

        return $item instanceof OrderItem && $typed !== null && self::isAtLoss($item, $typed);
    }

    private function paidUnitText(mixed $orderItemId): string
    {
        return Toman::format($this->orderItem($orderItemId)?->getPaidUnit() ?? 0);
    }

    private function orderItem(mixed $orderItemId): ?OrderItem
    {
        return blank($orderItemId) ? null : OrderItem::query()->whereKey((int) $orderItemId)->where(OrderItem::COLUMN_ORDER_ID, $this->purchase()->getOrderId())->first();
    }

    /**
     * «۵۰٬۰۰۰ تومان هر واحد · ۱۵۰٬۰۰۰ تومان برای ۳ عدد», filled from the chosen order item (read only).
     */
    private function customerPaid(mixed $orderItemId): ?string
    {
        $item = $this->orderItem($orderItemId);

        return $item instanceof OrderItem
            ? Toman::format($item->getPaidUnit()).' هر واحد · '.Toman::format($item->getPaidTotal()).' برای '.PersianDigits::toPersian($item->getQuantity()).' عدد'
            : null;
    }

    /**
     * The unit cost is at or above what the customer paid for one unit of the item (after haraj and coupon).
     */
    private static function isAtLoss(OrderItem $item, int $unitCost): bool
    {
        return $unitCost >= $item->getPaidUnit();
    }

    /**
     * After saving a row bought at or above what the customer paid per unit: a warning on top of the success.
     */
    private function warnIfAtLoss(OrderPurchaseItem $row): void
    {
        if (self::isAtLoss($row->getOrderItem(), $row->getUnitCost())) {
            Notification::make()
                ->warning()
                ->title('قیمت خرید '.$row->getOrderItem()->getProductName().' برابر یا بیشتر از پرداختی مشتری برای هر واحد است.')
                ->body('قیمت خرید واحد: '.Toman::format($row->getUnitCost()).' · پرداختی مشتری برای هر واحد: '.Toman::format($row->getOrderItem()->getPaidUnit()))
                ->persistent()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rowData(array $data): PurchaseItemData
    {
        return PurchaseItemData::fromArray($data);
    }

    private function purchase(): OrderPurchase
    {
        /** @var OrderPurchase */
        return $this->getOwnerRecord();
    }

    private function mayUpdate(): bool
    {
        return auth('admin')->user()?->can('update', $this->purchase()) ?? false;
    }

    /**
     * Adding, changing and removing rows: until 48 hours after the document was registered (owner, 2026-11-16).
     */
    private function mayChangeRows(): bool
    {
        return $this->mayUpdate() && ! $this->purchase()->refresh()->isLocked();
    }

    /**
     * Runs the change and shows the result; a refusal is shown with its reason. The document's header (status,
     * cost, margin) is refreshed on the page.
     */
    private function attempt(Closure $change, string $success): void
    {
        try {
            $change();
        } catch (PurchaseRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
        $this->dispatch('refresh-purchase');
    }
}
