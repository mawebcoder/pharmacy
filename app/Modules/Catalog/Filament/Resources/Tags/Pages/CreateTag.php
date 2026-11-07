<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Tags\Pages;

use App\Modules\Catalog\Actions\SaveTag;
use App\Modules\Catalog\Data\TagData;
use App\Modules\Catalog\Filament\Resources\Tags\TagResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTag::class)(TagData::fromArray($data));
    }
}
