<?php

declare(strict_types=1);

namespace App\Modules\Locations\Filament\Resources\Cities\Tables;

use App\Modules\Locations\Actions\SetCityDelivery;
use App\Modules\Locations\Data\CityDeliveryData;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('province'))
            ->description('شهرها و استان‌ها از فهرست تیپاکس می‌آیند (php artisan locations:import-tipax)؛ ارسال را برای شهرهایی که سفارش می‌گیرید فعال کنید.')
            ->columns([
                TextColumn::make(City::COLUMN_NAME_FA)
                    ->label('شهر')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('province.'.Province::COLUMN_NAME_FA)
                    ->label('استان')
                    ->searchable()
                    ->sortable(),
                TextColumn::make(City::COLUMN_ID)
                    ->label('شناسه تیپاکس')
                    ->searchable()
                    ->sortable(),
                IconColumn::make(City::COLUMN_HAS_TIPAX_BRANCH)
                    ->label('شعبه تیپاکس')
                    ->boolean(),
                ToggleColumn::make(City::COLUMN_IS_DELIVERY_ENABLED)
                    ->label('ارسال فعال')
                    ->disabled(fn (City $record): bool => ! (auth('admin')->user()?->can('update', $record) ?? false))
                    ->updateStateUsing(function (City $record, bool $state, SetCityDelivery $setCityDelivery): bool {
                        $setCityDelivery(CityDeliveryData::for([$record->getId()], $state));

                        return $state;
                    }),
            ])
            ->filters([
                SelectFilter::make(City::COLUMN_PROVINCE_ID)
                    ->label('استان')
                    ->relationship('province', Province::COLUMN_NAME_FA)
                    ->searchable()
                    ->preload(),
                TernaryFilter::make(City::COLUMN_HAS_TIPAX_BRANCH)
                    ->label('شعبه تیپاکس'),
                TernaryFilter::make(City::COLUMN_IS_DELIVERY_ENABLED)
                    ->label('ارسال فعال'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::deliveryBulkAction('enableDelivery', 'فعال کردن ارسال', true),
                    self::deliveryBulkAction('disableDelivery', 'غیرفعال کردن ارسال', false),
                ]),
            ])
            ->defaultSort(City::COLUMN_NAME_FA);
    }

    private static function deliveryBulkAction(string $name, string $label, bool $enabled): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($enabled ? Heroicon::OutlinedTruck : Heroicon::OutlinedNoSymbol)
            ->requiresConfirmation()
            ->authorize(fn (): bool => auth('admin')->user()?->can('update', new City) ?? false)
            ->action(function (Collection $records, SetCityDelivery $setCityDelivery) use ($enabled): void {
                $changed = $setCityDelivery(CityDeliveryData::for($records->modelKeys(), $enabled));

                Notification::make()->success()->title('ارسال برای '.PersianDigits::toPersian($changed).' شهر به‌روز شد.')->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
