<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\AdminUsers\Pages;

use App\Modules\Identity\Actions\CreateAdminUser as CreateAdminUserAction;
use App\Modules\Identity\Data\AdminUserData;
use App\Modules\Identity\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    /**
     * @param  array{name: string, email: string, password?: string|null, roles?: list<string>, is_active?: bool}  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAdminUserAction::class)(AdminUserData::fromArray($data));
    }
}
