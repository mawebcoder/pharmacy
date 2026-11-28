<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Tables;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The products placed in a category or subcategory (owner, 2026-11-28), read-only, for the «محصولات» relation
 * managers; products are changed on their own page.
 */
final class PlacedProductsTable
{
    /**
     * @param  Closure(): Builder<Product>  $products
     */
    public static function configure(Table $table, Closure $products): Table
    {
        return $table
            ->query(fn (): Builder => $products()->with(['brand', 'primaryCategory', 'primarySubcategory', 'media']))
            ->columns([
                ImageThumbnail::make(Product::IMAGE_GALLERY, 'product'),
                TextColumn::make(Product::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Product $record): ?string => $record->getNameEn())
                    ->searchable([Product::COLUMN_NAME_FA, Product::COLUMN_NAME_EN])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('brand.'.Brand::COLUMN_NAME_FA)
                    ->label('برند')
                    ->placeholder('تعیین نشده'),
                TextColumn::make('primaryCategory.'.Category::COLUMN_NAME_FA)
                    ->label('دسته اصلی')
                    ->placeholder('تعیین نشده')
                    ->toggleable(),
                TextColumn::make('primarySubcategory.'.Subcategory::COLUMN_NAME_FA)
                    ->label('زیردسته اصلی')
                    ->placeholder('تعیین نشده'),
                TextColumn::make(Product::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make(Product::COLUMN_STATUS)->label('وضعیت')->options(ProductStatus::options()),
            ])
            ->recordActions([
                Action::make('openProduct')
                    ->label('ویرایش محصول')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Product $record): bool => ProductResource::canEdit($record)),
            ])
            ->defaultSort(Product::COLUMN_NAME_FA);
    }
}
