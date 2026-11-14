<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Customers;

use App\Modules\Identity\Filament\Resources\Customers\Pages\ListCustomers;
use App\Modules\Identity\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Identity\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Modules\Identity\Filament\Resources\Customers\Tables\CustomersTable;
use App\Modules\Identity\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Storefront customers (roadmap D3.3, S17): a read-only list whose rows open the customer's page, with block and
 * unblock. Customers sign up themselves with an SMS code. The orders step adds their orders to the page.
 */
final class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'مشتریان';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مشتری';

    protected static ?string $pluralModelLabel = 'مشتریان';

    protected static ?string $recordTitleAttribute = Customer::COLUMN_MOBILE;

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
