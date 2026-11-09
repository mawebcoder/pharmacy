<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\Providers\Pages;

use App\Modules\Pricing\Actions\SaveProvider;
use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Filament\Resources\Providers\ProviderResource;
use App\Modules\Pricing\Models\Provider;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Provider $record */
        $adminId = auth('admin')->id();

        return app(SaveProvider::class)(ProviderData::fromArray($data), $record, $adminId === null ? null : (int) $adminId);
    }
}
