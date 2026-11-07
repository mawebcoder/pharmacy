<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Tags\Pages;

use App\Modules\Catalog\Actions\SaveTag;
use App\Modules\Catalog\Data\TagData;
use App\Modules\Catalog\Filament\Resources\Tags\TagResource;
use App\Modules\Catalog\Models\Tag;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Tag $record): string => route('tags.show', $record), shouldOpenInNewTab: true)
                ->visible(fn (Tag $record): bool => ! $record->trashed()),
            TagResource::deleteAction(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Tag $record */
        return app(SaveTag::class)(TagData::fromArray($data), $record);
    }
}
