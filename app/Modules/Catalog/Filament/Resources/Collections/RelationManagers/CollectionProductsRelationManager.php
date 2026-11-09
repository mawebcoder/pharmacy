<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Collections\RelationManagers;

use App\Modules\Catalog\Actions\AddProductsToCollection;
use App\Modules\Catalog\Actions\RemoveProductFromCollection;
use App\Modules\Catalog\Actions\ReorderCollectionProducts;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The products of a collection in the admin's order (drag and drop). Writes go through the collection actions, so
 * the search index follows.
 */
final class CollectionProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'محصولات کالکشن';

    protected static ?string $modelLabel = 'محصول';

    protected static ?string $pluralModelLabel = 'محصولات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! (auth('admin')->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('brand'))
            ->reorderable('collection_product.'.ProductCollection::PIVOT_SORT, fn (): bool => ! $this->isReadOnly())
            ->defaultSort('collection_product.'.ProductCollection::PIVOT_SORT)
            ->description(fn (): string => $this->collection()->isSystem()
                ? 'جعبه جستجوی خالی ۶ محصول اول موجود را به همین ترتیب نشان می‌دهد. ترتیب را با کشیدن تغییر دهید.'
                : 'ترتیب ردیف‌ها را با کشیدن تغییر دهید؛ ردیف صفحه اصلی و ترتیب «پیشنهادی» صفحه کالکشن از همین ترتیب پیروی می‌کنند.')
            ->columns([
                ImageThumbnail::make(Product::IMAGE_GALLERY, 'product'),
                TextColumn::make(Product::COLUMN_NAME_FA)->label('نام')->wrap(),
                TextColumn::make('brand.name_fa')->label('برند'),
                TextColumn::make(Product::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label()),
            ])
            ->headerActions([
                Action::make('addProducts')
                    ->label('افزودن محصول')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->schema([
                        // Picked by SKU (decided 2026-11-07, S13b); the SKUs' products join the collection.
                        ImageSelect::skus(Select::make('sku_ids'))
                            ->label('محصولات')
                            ->multiple()
                            ->required(),
                    ])
                    ->action(function (array $data, AddProductsToCollection $addProducts): void {
                        $addProducts($this->collection(), app(AdminOptions::class)->productIdsOfSkus(array_values((array) $data['sku_ids'])));
                        Notification::make()->success()->title('محصولات به کالکشن اضافه شدند.')->send();
                    }),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('برداشتن')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->action(fn (Product $record, RemoveProductFromCollection $remove) => $remove($this->collection(), $record)),
            ]);
    }

    /**
     * Drag and drop goes through ReorderCollectionProducts.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_if($this->isReadOnly(), 403);

        app(ReorderCollectionProducts::class)($this->collection(), array_values(array_map(intval(...), $order)));
    }

    private function collection(): ProductCollection
    {
        /** @var ProductCollection $collection */
        $collection = $this->getOwnerRecord();

        return $collection;
    }
}
