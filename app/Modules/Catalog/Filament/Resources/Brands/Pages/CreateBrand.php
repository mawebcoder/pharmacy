<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Brands\Pages;

use App\Modules\Catalog\Actions\CreateBrand as CreateBrandAction;
use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Filament\Resources\Brands\BrandResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateBrandAction::class)(BrandData::fromArray($data));
    }
}
