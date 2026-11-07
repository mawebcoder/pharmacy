<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Brands\Tables;

use App\Modules\Catalog\Actions\UpdateBrand;
use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Filament\Resources\Brands\BrandResource;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Shared\Support\Countries;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(Brand::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Brand $record): ?string => $record->getNameEn())
                    ->searchable([Brand::COLUMN_NAME_FA, Brand::COLUMN_NAME_EN])
                    ->sortable(),
                TextColumn::make(Brand::COLUMN_SLUG)
                    ->label('نامک')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make(Brand::COLUMN_COUNTRY)
                    ->label('کشور')
                    ->formatStateUsing(fn (?string $state): ?string => Countries::name($state))
                    ->placeholder('—')
                    ->sortable(),
                ToggleColumn::make(Brand::COLUMN_IS_ACTIVE)
                    ->label('فعال')
                    ->disabled(fn (Brand $record): bool => $record->trashed() || ! (auth('admin')->user()?->can('update', $record) ?? false))
                    ->updateStateUsing(fn (Brand $record, bool $state, UpdateBrand $updateBrand): bool => $updateBrand($record, BrandData::fromArray([...$record->attributesToArray(), Brand::COLUMN_IS_ACTIVE => $state]))->isActive()),
                TextColumn::make(Brand::COLUMN_UPDATED_AT)
                    ->label('آخرین تغییر')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make(Brand::COLUMN_IS_ACTIVE)->label('فعال'),
                SelectFilter::make(Brand::COLUMN_COUNTRY)
                    ->label('کشور')
                    ->options(Countries::options())
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                BrandResource::deleteAction(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort(Brand::COLUMN_NAME_FA);
    }
}
