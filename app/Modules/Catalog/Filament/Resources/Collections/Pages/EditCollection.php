<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Collections\Pages;

use App\Modules\Catalog\Actions\SaveCollection;
use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Filament\Resources\Collections\CollectionResource;
use App\Modules\Catalog\Models\ProductCollection;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (ProductCollection $record): string => route('collections.show', $record), shouldOpenInNewTab: true)
                ->visible(fn (ProductCollection $record): bool => ! $record->trashed()),
            CollectionResource::deleteAction(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductCollection $record */
        return app(SaveCollection::class)(CollectionData::fromArray([ProductCollection::COLUMN_SLUG => $record->getSlug(), ...$data]), $record);
    }
}
