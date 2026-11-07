<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories;

use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\RestoreCategory;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\ManageCategoryTree;
use App\Modules\Catalog\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Modules\Catalog\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Modules\Catalog\Models\Category;
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
 * Categories (roadmap S08, B6 Catalog group): a drag-and-drop tree, a searchable list, and create/edit forms.
 * Deleted categories stay in the trash and can be restored.
 */
final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'کاتالوگ';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'دسته‌بندی';

    protected static ?string $pluralModelLabel = 'دسته‌بندی‌ها';

    protected static ?string $recordTitleAttribute = Category::COLUMN_NAME_FA;

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    /**
     * Delete goes through DeleteCategory, which refuses categories with subcategories or products.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Category $record, DeleteCategory $deleteCategory, DeleteAction $action): bool => self::attempt($action, fn () => $deleteCategory($record)));
    }

    /**
     * Restore goes through RestoreCategory, which wants the parent restored first.
     */
    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->using(fn (Category $record, RestoreCategory $restoreCategory, RestoreAction $action): bool => self::attempt($action, fn () => $restoreCategory($record)));
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
        return [Category::COLUMN_NAME_FA, Category::COLUMN_NAME_EN, Category::COLUMN_SLUG];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategoryTree::route('/'),
            'list' => ListCategories::route('/list'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Runs a category action; a refusal becomes the action's failure notification.
     */
    private static function attempt(DeleteAction|RestoreAction $action, callable $change): bool
    {
        try {
            $change();
        } catch (CategoryChangeRefused $refused) {
            $action->failureNotificationTitle($refused->getMessage());

            return false;
        }

        return true;
    }
}
