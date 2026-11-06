<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\AdminUsers\Pages;

use App\Modules\Identity\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
