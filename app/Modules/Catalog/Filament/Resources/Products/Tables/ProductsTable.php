<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Tables;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['brand', 'primaryCategory', 'media'])->withCount('variants'))
            ->columns([
                SpatieMediaLibraryImageColumn::make(Product::IMAGE_GALLERY)
                    ->label('')
                    ->collection(Product::IMAGE_GALLERY)
                    ->conversion('product-80-webp')
                    ->limit(1)
                    ->square()
                    ->imageSize(40),
                TextColumn::make(Product::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Product $record): ?string => $record->getNameEn())
                    ->searchable([Product::COLUMN_NAME_FA, Product::COLUMN_NAME_EN, Product::COLUMN_IRC_CODE])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('brand.'.Brand::COLUMN_NAME_FA)
                    ->label('برند')
                    ->searchable(),
                TextColumn::make('primaryCategory.'.Category::COLUMN_NAME_FA)
                    ->label('دسته اصلی')
                    ->toggleable(),
                TextColumn::make(Product::COLUMN_PRODUCT_TYPE)
                    ->label('نوع')
                    ->formatStateUsing(fn (ProductType $state): string => $state->label())
                    ->toggleable(),
                TextColumn::make(Product::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => $state->color()),
                TextColumn::make('variants_count')
                    ->label('تنوع')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make(Product::COLUMN_UPDATED_AT)
                    ->label('آخرین تغییر')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make(Product::COLUMN_STATUS)->label('وضعیت')->options(ProductStatus::options()),
                SelectFilter::make(Product::COLUMN_PRODUCT_TYPE)->label('نوع')->options(ProductType::options()),
                SelectFilter::make(Product::COLUMN_BRAND_ID)
                    ->label('برند')
                    ->relationship('brand', Brand::COLUMN_NAME_FA)
                    ->searchable()
                    ->preload(),
                SelectFilter::make(Product::COLUMN_PRIMARY_CATEGORY_ID)
                    ->label('دسته اصلی')
                    ->relationship('primaryCategory', Category::COLUMN_NAME_FA)
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ProductResource::deleteAction(),
                ProductResource::restoreAction(),
            ])
            ->defaultSort(Product::COLUMN_UPDATED_AT, 'desc');
    }
}
