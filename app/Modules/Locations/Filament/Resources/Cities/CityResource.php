<?php

declare(strict_types=1);

namespace App\Modules\Locations\Filament\Resources\Cities;

use App\Modules\Locations\Filament\Resources\Cities\Pages\ListCities;
use App\Modules\Locations\Filament\Resources\Cities\Tables\CitiesTable;
use App\Modules\Locations\Models\City;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Cities from the official dataset (roadmap D4, D11.5): read-only except the delivery switch.
 */
final class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'ارسال';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'شهر';

    protected static ?string $pluralModelLabel = 'شهرها';

    protected static ?string $recordTitleAttribute = City::COLUMN_NAME_FA;

    public static function table(Table $table): Table
    {
        return CitiesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
        ];
    }
}
