<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products;

use App\Modules\Catalog\Actions\DeleteProduct;
use App\Modules\Catalog\Actions\RestoreProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\FaqsRelationManager;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\ProductRelationsRelationManager;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Filament\Resources\Products\Schemas\ProductForm;
use App\Modules\Catalog\Filament\Resources\Products\Tables\ProductsTable;
use App\Modules\Catalog\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Products (roadmap S10, B6 Catalog group, D15.3): tabs General → Content → Images → SEO, with variants, FAQs and
 * related products as relation managers. Deleted products stay in the trash with their variants.
 */
final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'محصول';

    protected static ?string $pluralModelLabel = 'محصولات';

    protected static ?string $recordTitleAttribute = Product::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('محصول و همه تنوع‌هایش به سطل زباله منتقل می‌شوند و قابل بازگردانی‌اند.')
            ->using(function (Product $record, DeleteProduct $deleteProduct): bool {
                $deleteProduct($record);

                return true;
            });
    }

    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->modalDescription('محصول با تنوع‌هایش بازگردانده و برای بازبینی «پنهان» می‌شود.')
            ->using(function (Product $record, RestoreProduct $restoreProduct): bool {
                $restoreProduct($record);

                return true;
            });
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [Product::COLUMN_NAME_FA, Product::COLUMN_NAME_EN, Product::COLUMN_SLUG, Product::COLUMN_IRC_CODE];
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            FaqsRelationManager::class,
            ProductRelationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
