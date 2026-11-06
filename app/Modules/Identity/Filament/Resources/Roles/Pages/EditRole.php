<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Roles\Pages;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as ShieldEditRole;
use Filament\Actions\DeleteAction;
use Override;
use Spatie\Permission\Models\Role;

/**
 * Built-in roles keep their name (code checks it) and cannot be deleted; their permissions can still change.
 */
final class EditRole extends ShieldEditRole
{
    protected static string $resource = RoleResource::class;

    #[Override]
    protected function getActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Role $record): bool => AdminRole::isBuiltIn($record->name)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        /** @var Role $role */
        $role = $this->getRecord();

        if (AdminRole::isBuiltIn($role->name)) {
            $data['name'] = $role->name;
        }

        return $data;
    }
}
