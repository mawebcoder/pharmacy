<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Pages;

use App\Modules\Catalog\Actions\MoveCategories;
use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Resources\Pages\TreePage;

/**
 * The category tree: drag to reorder or to move a category under another one (at most Category::MAX_DEPTH levels).
 * Changes are saved by MoveCategories; editing and deleting happen on the edit page.
 */
final class ManageCategoryTree extends TreePage
{
    protected static string $resource = CategoryResource::class;

    protected static int $maxDepth = Category::MAX_DEPTH;

    public function getTitle(): string
    {
        return 'دسته‌بندی‌ها';
    }

    public function getSubheading(): string
    {
        return 'برای تغییر ترتیب یا جابه‌جایی زیر دسته دیگر، دسته‌ها را بکشید و رها کنید و سپس «ذخیره» را بزنید. حداکثر '
            .PersianDigits::toPersian(Category::MAX_DEPTH).' سطح.';
    }

    /**
     * @param  array<int, array{id: int|string, children?: array<int, mixed>}>|null  $list
     * @return array{reload: bool}
     */
    public function updateTree(?array $list = null): array
    {
        abort_unless(auth('admin')->user()?->can('reorder', Category::class) ?? false, 403);

        try {
            $moved = app(MoveCategories::class)(CategoryPositionData::fromNestedList($list ?? []));
        } catch (CategoryChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();
            $this->dispatch('refreshTree');

            return ['reload' => true];
        }

        if ($moved > 0) {
            Notification::make()->success()->title('ترتیب دسته‌ها ذخیره شد.')->send();
            $this->dispatch('refreshTree');
        }

        return ['reload' => $moved > 0];
    }

    public function getTreeRecordTitle(?Model $record = null): string
    {
        return $record instanceof Category ? $record->getNameFa() : '';
    }

    public function getTreeRecordDescription(?Model $record = null): ?string
    {
        return $record instanceof Category && ! $record->isActive() ? 'غیرفعال' : null;
    }

    public function getTreeRecordIcon(?Model $record = null): ?string
    {
        return null;
    }

    public function getTreeRootLevelKey(): null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('فهرست و جستجو')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(CategoryResource::getUrl('list')),
            CreateAction::make()->url(CategoryResource::getUrl('create')),
        ];
    }

    protected function hasCreateAction(): bool
    {
        return false;
    }

    protected function hasDeleteAction(): bool
    {
        return false;
    }

    protected function getEditAction(): EditAction
    {
        return EditAction::make()->url(fn (Category $record): string => CategoryResource::getUrl('edit', ['record' => $record]));
    }
}
