<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\Pages;

use App\Modules\Catalog\Actions\SaveAttribute;
use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Filament\Resources\Attributes\AttributeResource;
use App\Modules\Catalog\Models\Attribute;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [AttributeResource::deleteAction(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Attribute $record */
        try {
            return app(SaveAttribute::class)(
                (string) $data[Attribute::COLUMN_NAME_FA],
                (string) $data[Attribute::COLUMN_CODE],
                AttributeType::from((string) $data[Attribute::COLUMN_TYPE]),
                (bool) ($data[Attribute::COLUMN_IS_FILTERABLE] ?? true),
                blank($data[Attribute::COLUMN_SORT] ?? null) ? null : (int) $data[Attribute::COLUMN_SORT],
                $record,
            );
        } catch (AttributeChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
