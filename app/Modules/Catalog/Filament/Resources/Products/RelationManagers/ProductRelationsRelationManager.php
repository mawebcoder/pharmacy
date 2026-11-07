<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Actions\LinkProducts;
use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductRelation;
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
                Select::make(ProductRelation::COLUMN_RELATED_PRODUCT_ID)
                    ->label('محصول')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => Product::query()
                        ->whereKeyNot($this->getOwnerRecord()->getKey())
                        ->where(fn ($query) => $query
                            ->where(Product::COLUMN_NAME_FA, 'like', "%{$search}%")
                            ->orWhere(Product::COLUMN_NAME_EN, 'like', "%{$search}%"))
                        ->limit(20)
                        ->pluck(Product::COLUMN_NAME_FA, Product::COLUMN_ID)
                        ->all())
                    ->getOptionLabelUsing(fn (int|string $value): ?string => Product::withTrashed()->find($value)?->getNameFa()),
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
                            return $linkProducts($product, (int) $data[ProductRelation::COLUMN_RELATED_PRODUCT_ID], ProductRelationType::from((string) $data[ProductRelation::COLUMN_TYPE]));
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
