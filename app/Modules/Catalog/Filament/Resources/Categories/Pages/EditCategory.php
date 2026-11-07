<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Pages;

use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Filament\Resources\Categories\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Category $record): string => route('category.show', $record), shouldOpenInNewTab: true)
                ->visible(fn (Category $record): bool => $record->isActive() && ! $record->trashed()),
            CategoryResource::deleteAction(),
            CategoryResource::restoreAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Category $record */
        try {
            return app(UpdateCategory::class)($record, CategoryData::fromArray($data));
        } catch (CategoryChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
