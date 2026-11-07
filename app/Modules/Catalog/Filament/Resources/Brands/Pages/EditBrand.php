<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Brands\Pages;

use App\Modules\Catalog\Actions\UpdateBrand;
use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Filament\Resources\Brands\BrandResource;
use App\Modules\Catalog\Models\Brand;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Brand $record): string => route('brands.show', $record), shouldOpenInNewTab: true)
                ->visible(fn (Brand $record): bool => $record->isActive() && ! $record->trashed()),
            BrandResource::deleteAction(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Brand $record */
        return app(UpdateBrand::class)($record, BrandData::fromArray($data));
    }
}
