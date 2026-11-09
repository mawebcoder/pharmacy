<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Actions\LinkProducts;
use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductRelation;
use App\Modules\Catalog\Queries\AdminOptions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Related, alternative and frequently-bought-together products, shown on the product page (S13).
 */
final class ProductRelationsRelationManager extends RelationManager
{
    use AuthorizesThroughProduct;

    protected static string $relationship = 'productRelations';

    protected static ?string $title = 'محصولات مرتبط';

    protected static ?string $modelLabel = 'محصول مرتبط';

    protected static ?string $pluralModelLabel = 'محصولات مرتبط';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // Picked by SKU (decided 2026-11-07, S13b); the SKU's product is linked.
                ImageSelect::skus(Select::make('related_sku_id'))
                    ->label('محصول (SKU)')
                    ->required(),
                Select::make(ProductRelation::COLUMN_TYPE)
                    ->label('نوع ارتباط')
                    ->options(ProductRelationType::options())
                    ->default(ProductRelationType::Related->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('relatedProduct'))
            ->columns([
                TextColumn::make('relatedProduct.'.Product::COLUMN_NAME_FA)->label('محصول'),
                TextColumn::make(ProductRelation::COLUMN_TYPE)
                    ->label('نوع ارتباط')
                    ->badge()
                    ->formatStateUsing(fn (ProductRelationType $state): string => $state->label()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canChange())
                    ->using(function (array $data, LinkProducts $linkProducts): ProductRelation {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        try {
                            $relatedProductId = app(AdminOptions::class)->productIdsOfSkus([(int) $data['related_sku_id']])[0] ?? 0;

                            return $linkProducts($product, $relatedProductId, ProductRelationType::from((string) $data[ProductRelation::COLUMN_TYPE]));
                        } catch (ProductChangeRefused $refused) {
                            Notification::make()->danger()->title($refused->getMessage())->send();
                            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
                        }
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->visible(fn (): bool => $this->canChange()),
            ]);
    }
}
