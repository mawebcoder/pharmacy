<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Collections\Pages;

use App\Modules\Catalog\Actions\SaveCollection;
use App\Modules\Catalog\Data\CollectionData;
use App\Modules\Catalog\Filament\Resources\Collections\CollectionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveCollection::class)(CollectionData::fromArray($data));
    }

    /**
     * Products are added on the edit page.
     */
    protected function getRedirectUrl(): string
    {
        return CollectionResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
