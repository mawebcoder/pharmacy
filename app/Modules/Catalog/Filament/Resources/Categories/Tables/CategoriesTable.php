<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Tables;

use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parent'))
            ->columns([
                ImageThumbnail::make(Category::IMAGE_ICON, 'category_icon'),
                TextColumn::make(Category::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Category $record): ?string => $record->getNameEn())
                    ->searchable([Category::COLUMN_NAME_FA, Category::COLUMN_NAME_EN])
                    ->sortable(),
                TextColumn::make('parent.'.Category::COLUMN_NAME_FA)
                    ->label('دسته والد')
                    ->placeholder('سطح اول')
                    ->searchable(),
                TextColumn::make(Category::COLUMN_SLUG)
                    ->label('نامک')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make(Category::COLUMN_IS_ACTIVE)
                    ->label('فعال')
                    ->disabled(fn (Category $record): bool => $record->trashed() || ! (auth('admin')->user()?->can('update', $record) ?? false))
                    ->updateStateUsing(fn (Category $record, bool $state, UpdateCategory $updateCategory): bool => $updateCategory(
                        $record,
                        CategoryData::fromArray([...$record->attributesToArray(), Category::COLUMN_IS_ACTIVE => $state]),
                    )->isActive()),
                TextColumn::make(Category::COLUMN_UPDATED_AT)
                    ->label('آخرین تغییر')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make(Category::COLUMN_IS_ACTIVE)->label('فعال'),
                SelectFilter::make(Category::COLUMN_PARENT_ID)
                    ->label('دسته والد')
                    ->relationship('parent', Category::COLUMN_NAME_FA)
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                CategoryResource::deleteAction(),
                CategoryResource::restoreAction(),
            ])
            ->defaultSort(Category::COLUMN_NAME_FA);
    }
}
