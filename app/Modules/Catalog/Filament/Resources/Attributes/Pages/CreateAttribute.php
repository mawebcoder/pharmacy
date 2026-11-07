<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Pages;

use App\Modules\Catalog\Actions\SaveAttribute;
use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Filament\Resources\Attributes\AttributeResource;
use App\Modules\Catalog\Models\Attribute;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * After creating an attribute, its values are added on the edit page.
 */
final class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveAttribute::class)(
            (string) $data[Attribute::COLUMN_NAME_FA],
            (string) $data[Attribute::COLUMN_CODE],
            AttributeType::from((string) $data[Attribute::COLUMN_TYPE]),
            (bool) ($data[Attribute::COLUMN_IS_FILTERABLE] ?? true),
            blank($data[Attribute::COLUMN_SORT] ?? null) ? null : (int) $data[Attribute::COLUMN_SORT],
        );
    }

    protected function getRedirectUrl(): string
    {
        return AttributeResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
