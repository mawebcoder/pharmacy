<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Pages\Pages;

use App\Modules\Content\Actions\SavePage;
use App\Modules\Content\Data\PageData;
use App\Modules\Content\Filament\Resources\Pages\PageResource;
use App\Modules\Content\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('مشاهده صفحه')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true)
                ->visible(fn (Page $record): bool => $record->isActive() && ! $record->trashed()),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        return app(SavePage::class)(PageData::fromArray($data), $record);
    }
}
