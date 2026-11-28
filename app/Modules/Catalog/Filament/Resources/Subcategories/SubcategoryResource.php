<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories;

use App\Modules\Catalog\Filament\Resources\Subcategories\Pages\EditSubcategory;
use App\Modules\Catalog\Filament\Resources\Subcategories\Pages\ListSubcategories;
use App\Modules\Catalog\Filament\Resources\Subcategories\Pages\ViewSubcategory;
use App\Modules\Catalog\Filament\Resources\Subcategories\RelationManagers\SubcategoryProductsRelationManager;
use App\Modules\Catalog\Filament\Resources\Subcategories\Schemas\SubcategoryForm;
use App\Modules\Catalog\Filament\Resources\Subcategories\Tables\SubcategoriesTable;
use App\Modules\Catalog\Models\Subcategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «زیردسته‌ها» in the Catalog group (owner, 2026-11-28): every subcategory with its category, a view page and an edit
 * page that can move it to another category; both list its products. New ones are added in a category's
 * «زیردسته‌ها» tab. Permissions are the category's (SubcategoryPolicy).
 */
final class SubcategoryResource extends Resource
{
    protected static ?string $model = Subcategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'زیردسته';

    protected static ?string $pluralModelLabel = 'زیردسته‌ها';

    protected static ?string $recordTitleAttribute = Subcategory::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        return SubcategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubcategoriesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            SubcategoryProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubcategories::route('/'),
            'view' => ViewSubcategory::route('/{record}'),
            'edit' => EditSubcategory::route('/{record}/edit'),
        ];
    }
}
