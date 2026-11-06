<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\AdminUsers\Pages;

use App\Modules\Identity\Actions\UpdateAdminUser;
use App\Modules\Identity\Data\AdminUserData;
use App\Modules\Identity\Exceptions\AdminUserChangeRefused;
use App\Modules\Identity\Filament\Resources\AdminUsers\AdminUserResource;
use App\Modules\Identity\Models\AdminUser;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * No delete action: admin accounts are deactivated instead.
 */
final class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var AdminUser $record */
        $record = $this->getRecord();

        $data['roles'] = $record->getRoleNames()->all();
        unset($data['password']);

        return $data;
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, roles?: list<string>, is_active?: bool}  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var AdminUser $record */
        /** @var AdminUser $actor */
        $actor = auth('admin')->user();

        try {
            return app(UpdateAdminUser::class)($record, AdminUserData::fromArray($data), $actor);
        } catch (AdminUserChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
