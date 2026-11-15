<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders;

use App\Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Modules\Orders\Filament\Resources\Orders\Tables\OrdersTable;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Filament\RelationManagers\OrderPaymentsRelationManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Orders (roadmap S29, D3.5): a list whose rows open the order's page, built only from the order's snapshots. Status
 * changes, cancelling and the refund record are header actions on that page (ViewOrder). Orders are placed by
 * customers at checkout, never created or deleted here.
 */
final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'سفارش';

    protected static ?string $pluralModelLabel = 'سفارش‌ها';

    protected static ?string $recordTitleAttribute = Order::COLUMN_NUMBER;

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [OrderPaymentsRelationManager::class];
    }

    public static function canCreate(): bool
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
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
