<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Purchases;

use App\Modules\Orders\Filament\Resources\Purchases\Pages\ListOrderPurchases;
use App\Modules\Orders\Filament\Resources\Purchases\Pages\ViewOrderPurchase;
use App\Modules\Orders\Filament\Resources\Purchases\RelationManagers\PurchaseItemsRelationManager;
use App\Modules\Orders\Filament\Resources\Purchases\Schemas\OrderPurchaseInfolist;
use App\Modules\Orders\Filament\Resources\Purchases\Tables\OrderPurchasesTable;
use App\Modules\Orders\Models\OrderPurchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Purchase documents («اسناد خرید», roadmap S25, owner 2026-11-16): one per order, opened from the order's page
 * («ثبت سند خرید») and linked both ways. The document's page holds its rows (supplier, quantity, unit cost per order
 * item), receiving, and the final margin. Never created here directly, never deleted.
 */
final class OrderPurchaseResource extends Resource
{
    protected static ?string $model = OrderPurchase::class;

    protected static ?string $slug = 'order-purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'سند خرید';

    protected static ?string $pluralModelLabel = 'اسناد خرید';

    public static function infolist(Schema $schema): Schema
    {
        return OrderPurchaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderPurchasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PurchaseItemsRelationManager::class];
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
            'index' => ListOrderPurchases::route('/'),
            'view' => ViewOrderPurchase::route('/{record}'),
        ];
    }
}
