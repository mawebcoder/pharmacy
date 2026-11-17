<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Resources\Pharmacists\Pages;

use App\Modules\Settings\Filament\Resources\Pharmacists\PharmacistResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

final class ManagePharmacists extends ManageRecords
{
    protected static string $resource = PharmacistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('مشاهده صفحه')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('pharmacist'), shouldOpenInNewTab: true),
            PharmacistResource::createAction(),
        ];
    }
}
