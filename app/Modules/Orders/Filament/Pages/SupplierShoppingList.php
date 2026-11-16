<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Pages;

use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Support\SupplierListSpreadsheet;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * «خرید از تأمین‌کننده» (roadmap S30, D13.6): the purchase document rows still to arrive, grouped by supplier, to
 * send to each supplier by print or Excel. Rows come only from documents the admins filled; each row opens its
 * document.
 */
final class SupplierShoppingList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'supplier-shopping-list';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'خرید از تأمین‌کننده';

    protected static ?string $title = 'خرید از تأمین‌کننده';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('viewAny', OrderPurchase::class) ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('چاپ')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->alpineClickHandler('window.print()'),
            Action::make('excel')
                ->label('دانلود اکسل')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): BinaryFileResponse {
                    $path = tempnam(sys_get_temp_dir(), 'supplier-list-').'.xlsx';
                    /** @var iterable<OrderPurchaseItem> $rows */
                    $rows = $this->getFilteredTableQuery()?->get() ?? [];
                    SupplierListSpreadsheet::write($path, $rows);

                    return response()->download($path, 'supplier-shopping-list.xlsx')->deleteFileAfterSend();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::openRows())
            ->defaultGroup(Group::make(OrderPurchaseItem::COLUMN_PROVIDER_NAME)->label('تأمین‌کننده')->collapsible())
            ->defaultSort(OrderPurchaseItem::COLUMN_ID)
            ->paginated([50, 100, 'all'])
            ->columns([
                TextColumn::make(OrderPurchaseItem::COLUMN_PROVIDER_NAME)->label('تأمین‌کننده')->searchable(),
                TextColumn::make('purchase_number')
                    ->label('سند خرید')
                    ->state(fn (OrderPurchaseItem $record): string => $record->purchase?->getNumber() ?? '')
                    ->url(fn (OrderPurchaseItem $record): string => OrderPurchaseResource::getUrl('view', ['record' => $record->getOrderPurchaseId()]))
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('orderItem.'.OrderItem::COLUMN_PRODUCT_NAME)
                    ->label('کالا')
                    ->description(fn (OrderPurchaseItem $record): string => trim(($record->getOrderItem()->getVariantTitle() ?? '').' · '.$record->getOrderItem()->getSku(), ' ·')),
                TextColumn::make('open_quantity')
                    ->label('تعداد باقی‌مانده')
                    ->state(fn (OrderPurchaseItem $record): int => $record->getOpenQuantity())
                    ->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                TextColumn::make(OrderPurchaseItem::COLUMN_UNIT_COST)->label('قیمت خرید واحد')->formatStateUsing(fn (int $state): string => Toman::format($state)),
                TextColumn::make('open_total')
                    ->label('جمع')
                    ->state(fn (OrderPurchaseItem $record): int => $record->getOpenQuantity() * $record->getUnitCost())
                    ->formatStateUsing(fn (int $state): string => Toman::format($state))
                    ->weight('bold'),
            ])
            ->filters([
                SelectFilter::make(OrderPurchaseItem::COLUMN_PROVIDER_ID)
                    ->label('تأمین‌کننده')
                    ->options(fn (): array => self::openRows()->pluck(OrderPurchaseItem::COLUMN_PROVIDER_NAME, OrderPurchaseItem::COLUMN_PROVIDER_ID)->filter()->all()),
            ]);
    }

    /**
     * Rows with units neither received nor marked missing.
     *
     * @return Builder<OrderPurchaseItem>
     */
    public static function openRows(): Builder
    {
        return OrderPurchaseItem::query()
            ->with(['orderItem', 'purchase.order'])
            ->whereRaw(OrderPurchaseItem::COLUMN_QUANTITY.' > '.OrderPurchaseItem::COLUMN_RECEIVED_QUANTITY.' + '.OrderPurchaseItem::COLUMN_MISSING_QUANTITY);
    }
}
