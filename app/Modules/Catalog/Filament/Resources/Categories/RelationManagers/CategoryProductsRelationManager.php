<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\RelationManagers;

use App\Modules\Catalog\Filament\Resources\Products\Tables\PlacedProductsTable;
use App\Modules\Catalog\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * «محصولات» of a category (owner, 2026-11-28): every product the category page lists, from its own main or extra
 * placement or from one of its subcategories (Product::inCategory()). Read-only.
 */
final class CategoryProductsRelationManager extends RelationManager
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
        return PlacedProductsTable::configure($table, fn () => Product::query()->inCategory((int) $this->getOwnerRecord()->getKey()));
    }
}
