<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Brands;

use App\Modules\Catalog\Actions\DeleteBrand;
use App\Modules\Catalog\Exceptions\BrandHasProducts;
use App\Modules\Catalog\Filament\Resources\Brands\Pages\CreateBrand;
use App\Modules\Catalog\Filament\Resources\Brands\Pages\EditBrand;
use App\Modules\Catalog\Filament\Resources\Brands\Pages\ListBrands;
use App\Modules\Catalog\Filament\Resources\Brands\Schemas\BrandForm;
use App\Modules\Catalog\Filament\Resources\Brands\Tables\BrandsTable;
use App\Modules\Catalog\Models\Brand;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Brands (roadmap S07, B6 Catalog group). Deleted brands stay in the trash and can be restored.
 */
final class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'برند';

    protected static ?string $pluralModelLabel = 'برندها';

    protected static ?string $recordTitleAttribute = Brand::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        return BrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    /**
     * Delete goes through DeleteBrand, which refuses brands that still have products.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(function (Brand $record, DeleteBrand $deleteBrand, DeleteAction $action): bool {
                try {
                    $deleteBrand($record);
                } catch (BrandHasProducts $refused) {
                    $action->failureNotificationTitle($refused->getMessage());

                    return false;
                }

                return true;
            });
    }

    /**
     * Lets the edit page open deleted brands so they can be restored.
     *
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
        return [Brand::COLUMN_NAME_FA, Brand::COLUMN_NAME_EN, Brand::COLUMN_SLUG];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }
}
