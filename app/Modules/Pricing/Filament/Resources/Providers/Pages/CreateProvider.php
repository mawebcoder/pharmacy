<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\Providers\Pages;

use App\Modules\Pricing\Actions\SaveProvider;
use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Filament\Resources\Providers\ProviderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateProvider extends CreateRecord
{
    protected static string $resource = ProviderResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $adminId = auth('admin')->id();

        return app(SaveProvider::class)(ProviderData::fromArray($data), adminId: $adminId === null ? null : (int) $adminId);
    }
}
