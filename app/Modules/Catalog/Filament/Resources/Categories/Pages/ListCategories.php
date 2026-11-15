<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Pages;

use App\Modules\Catalog\Actions\MoveCategories;
use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

/**
 * The categories, one level (owner, 2026-11-15); drag rows to reorder them. Subcategories are managed on a category's
 * page, in «زیردسته‌ها».
 */
final class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    /**
     * Reordering goes through MoveCategories, like every other change.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless(auth('admin')->user()?->can('reorder', Category::class) ?? false, 403);

        if (app(MoveCategories::class)(CategoryPositionData::fromOrderedIds($order)) > 0) {
            Notification::make()->success()->title('ترتیب دسته‌ها ذخیره شد.')->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
