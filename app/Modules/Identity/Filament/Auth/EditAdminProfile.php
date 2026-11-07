<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use App\Modules\Identity\Models\AdminUser;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Filament's profile page, saving through the model's setters: models have no $fillable (roadmap C4).
 */
final class EditAdminProfile extends EditProfile
{
    /**
     * @param  array<string, mixed>  $data  name, email and, when changed, the already-hashed password
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var AdminUser $record */
        $record->setName((string) $data[AdminUser::COLUMN_NAME])->setEmail((string) $data[AdminUser::COLUMN_EMAIL]);

        if (filled($data[AdminUser::COLUMN_PASSWORD] ?? null)) {
            $record->setPassword((string) $data[AdminUser::COLUMN_PASSWORD]);
        }

        $record->save();

        return $record;
    }
}
