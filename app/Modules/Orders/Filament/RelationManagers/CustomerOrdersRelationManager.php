<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\RelationManagers;

use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Tables\OrdersTable;
use App\Modules\Orders\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «سفارش‌ها» on a customer's admin page (roadmap S24, decided 2026-11-14): the customer's orders, newest first; a row
 * opens the order's page in a new tab. The relation is built here from the customer record, so the Identity module
 * does not need the Order model (module boundaries, C3).
 */
final class CustomerOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'سفارش‌ها';

    protected static ?string $modelLabel = 'سفارش';

    protected static ?string $pluralModelLabel = 'سفارش‌ها';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('viewAny', Order::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * @return HasMany<Order, Model>
     */
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(Order::class, Order::COLUMN_CUSTOMER_ID);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(OrdersTable::columns())
            ->defaultSort(Order::COLUMN_PLACED_AT, 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]), shouldOpenInNewTab: true);
    }
}
