<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Tables;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['brand', 'primaryCategory', 'primarySubcategory', 'media'])->withCount('variants')->withExists(['variants as has_near_expiry' => fn (Builder $variants): Builder => $variants->where(ProductVariant::COLUMN_IS_NEAR_EXPIRY, true)]))
            ->columns([
                ImageThumbnail::make(Product::IMAGE_GALLERY, 'product'),
                TextColumn::make(Product::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Product $record): ?string => $record->getNameEn())
                    ->searchable([Product::COLUMN_NAME_FA, Product::COLUMN_NAME_EN, Product::COLUMN_IRC_CODE])
                    ->sortable()
                    ->wrap(),
                // An imported product may have none yet (owner, 2026-11-27); it is not shown in the shop until set.
                TextColumn::make('brand.'.Brand::COLUMN_NAME_FA)
                    ->label('برند')
                    ->placeholder('تعیین نشده')
                    ->searchable(),
                TextColumn::make('primaryCategory.'.Category::COLUMN_NAME_FA)
                    ->label('دسته اصلی')
                    ->placeholder('تعیین نشده')
                    ->toggleable(),
                TextColumn::make('primarySubcategory.'.Subcategory::COLUMN_NAME_FA)
                    ->label('زیردسته اصلی')
                    ->placeholder('تعیین نشده')
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
                TextColumn::make('has_near_expiry')
                    ->label('زودمصرف')
                    ->badge()
                    ->state(fn (Product $record): ?string => $record->getAttribute('has_near_expiry') ? 'زودمصرف' : null)
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(),
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
                // To find the products to put on haraj (owner, 2026-11-16).
                Filter::make('near_expiry')
                    ->label('زودمصرف')
                    ->toggle()
                    // A SKU whose selling supplier has near-expiry stock (owner, 2026-11-17).
                    ->query(fn (Builder $query): Builder => $query->whereHas('variants', fn (Builder $variants): Builder => $variants->where(ProductVariant::COLUMN_IS_NEAR_EXPIRY, true))),
                // Imported products still missing what the shop needs (owner, 2026-11-27): they cannot be sold or shown.
                Filter::make('incomplete')
                    ->label('بدون برند، دسته یا زیردسته (غیرقابل فروش)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $missing): Builder => $missing
                        ->whereNull(Product::COLUMN_BRAND_ID)
                        ->orWhereNull(Product::COLUMN_PRIMARY_CATEGORY_ID)
                        ->orWhereNull(Product::COLUMN_PRIMARY_SUBCATEGORY_ID))),
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
