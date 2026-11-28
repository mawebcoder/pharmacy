<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories\Pages;

use App\Modules\Catalog\Actions\SaveSubcategory;
use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Filament\Resources\Subcategories\SubcategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Edits a subcategory; another main category moves it there with its products (SaveSubcategory).
 */
final class EditSubcategory extends EditRecord
{
    protected static string $resource = SubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Subcategory $record): string => $record->url(), shouldOpenInNewTab: true)
                ->visible(fn (Subcategory $record): bool => $record->isShownOnStorefront()),
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Subcategory $record */
        $category = Category::withTrashed()->findOrFail((int) $data[Subcategory::COLUMN_CATEGORY_ID]);

        try {
            return app(SaveSubcategory::class)($category, SubcategoryData::fromArray($data), $record);
        } catch (CategoryChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
