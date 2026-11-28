<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories\RelationManagers;

use App\Modules\Catalog\Filament\Resources\Products\Tables\PlacedProductsTable;
use App\Modules\Catalog\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * «محصولات» of a subcategory (owner, 2026-11-28): the products whose main or extra subcategory it is
 * (Product::inSubcategory()), as its page lists them. Read-only.
 */
final class SubcategoryProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'primaryProducts';

    protected static ?string $title = 'محصولات';

    protected static ?string $modelLabel = 'محصول';

    protected static ?string $pluralModelLabel = 'محصولات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return PlacedProductsTable::configure($table, fn () => Product::query()->inSubcategory((int) $this->getOwnerRecord()->getKey()));
    }
}
