<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Pages\Pages;

use App\Modules\Content\Actions\SavePage;
use App\Modules\Content\Data\PageData;
use App\Modules\Content\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SavePage::class)(PageData::fromArray($data));
    }
}
