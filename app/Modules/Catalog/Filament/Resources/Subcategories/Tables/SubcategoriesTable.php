<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories\Tables;

use App\Modules\Catalog\Actions\SaveSubcategory;
use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Every subcategory in menu order (its category's, then its own), with the number of products it lists: main or
 * extra subcategory, as its «محصولات» tab (Product::inSubcategory()).
 */
final class SubcategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'media'])->addSelect(['products_count' => self::productsCount()]))
            ->columns([
                ImageThumbnail::make(Subcategory::IMAGE_ICON, 'category_icon'),
                TextColumn::make(Subcategory::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Subcategory $record): ?string => $record->getNameEn())
                    ->searchable([Subcategory::COLUMN_NAME_FA, Subcategory::COLUMN_NAME_EN])
                    ->sortable(),
                TextColumn::make('category.'.Category::COLUMN_NAME_FA)
                    ->label('دسته اصلی'),
                TextColumn::make('products_count')
                    ->label('محصولات')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make(Subcategory::COLUMN_SLUG)
                    ->label('نامک')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make(Subcategory::COLUMN_IS_ACTIVE)
                    ->label('فعال')
                    ->disabled(fn (Subcategory $record): bool => ! (auth('admin')->user()?->can('update', $record) ?? false))
                    ->updateStateUsing(fn (Subcategory $record, bool $state, SaveSubcategory $save): bool => $save(
                        Category::withTrashed()->findOrFail($record->getCategoryId()),
                        SubcategoryData::fromArray([...$record->attributesToArray(), Subcategory::COLUMN_IS_ACTIVE => $state]),
                        $record,
                    )->isActive()),
            ])
            ->filters([
                SelectFilter::make(Subcategory::COLUMN_CATEGORY_ID)
                    ->label('دسته اصلی')
                    ->relationship('category', Category::COLUMN_NAME_FA)
                    ->searchable()
                    ->preload(),
                TernaryFilter::make(Subcategory::COLUMN_IS_ACTIVE)->label('فعال'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy(Category::query()->select(Category::COLUMN_SORT)->whereColumn('categories.id', 'subcategories.'.Subcategory::COLUMN_CATEGORY_ID))
                ->orderBy('subcategories.'.Subcategory::COLUMN_SORT)
                ->orderBy('subcategories.'.Subcategory::COLUMN_ID));
    }

    /**
     * Not deleted products whose main or extra subcategory the row is.
     *
     * @return Builder<Product>
     */
    private static function productsCount(): Builder
    {
        return Product::query()
            ->selectRaw('count(*)')
            ->where(fn (Builder $placed): Builder => $placed
                ->whereColumn('products.'.Product::COLUMN_PRIMARY_SUBCATEGORY_ID, 'subcategories.'.Subcategory::COLUMN_ID)
                ->orWhereExists(fn (QueryBuilder $extra): QueryBuilder => $extra
                    ->from('product_subcategory')
                    ->whereColumn('product_subcategory.product_id', 'products.'.Product::COLUMN_ID)
                    ->whereColumn('product_subcategory.subcategory_id', 'subcategories.'.Subcategory::COLUMN_ID)));
    }
}
