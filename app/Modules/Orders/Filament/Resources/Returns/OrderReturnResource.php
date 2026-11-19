<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns;

use App\Modules\Orders\Filament\Resources\Returns\Pages\CreateOrderReturn;
use App\Modules\Orders\Filament\Resources\Returns\Pages\ListOrderReturns;
use App\Modules\Orders\Filament\Resources\Returns\Pages\ViewOrderReturn;
use App\Modules\Orders\Filament\Resources\Returns\Schemas\OrderReturnForm;
use App\Modules\Orders\Filament\Resources\Returns\Schemas\OrderReturnInfolist;
use App\Modules\Orders\Filament\Resources\Returns\Tables\OrderReturnsTable;
use App\Modules\Orders\Models\OrderReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Order returns («مرجوعی‌ها», roadmap S27, owner 2026-11-19): opened here or from the order's page («ثبت مرجوعی») over
 * the units no other open return holds; approved (registered with Tipax) or cancelled on their page. Never edited
 * after opening (cancel and open another), never deleted.
 */
final class OrderReturnResource extends Resource
{
    protected static ?string $model = OrderReturn::class;

    protected static ?string $slug = 'order-returns';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مرجوعی';

    protected static ?string $pluralModelLabel = 'مرجوعی‌ها';

    public static function form(Schema $schema): Schema
    {
        return OrderReturnForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderReturnInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderReturnsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
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
            'index' => ListOrderReturns::route('/'),
            'create' => CreateOrderReturn::route('/create'),
            'view' => ViewOrderReturn::route('/{record}'),
        ];
    }
}
