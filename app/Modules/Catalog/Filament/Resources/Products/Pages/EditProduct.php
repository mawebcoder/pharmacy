<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Pages;

use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Filament\Resources\Products\Schemas\ProductForm;
use App\Modules\Catalog\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductResource::deleteAction(),
            ProductResource::restoreAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $record */
        $record = $this->getRecord();
        $data['category_ids'] = $record->categories()->pluck('categories.id')->all();
        $data['attribute_values'] = ProductForm::attributeState($record);
        $data['tag_ids'] = $record->tags()->pluck('tags.id')->all();

        return $data;
    }

    /**
     * Photos are saved before the product, so uploading the first photo and activating work in one save. When the
     * product change is refused, the photos are kept (the save is halted without rolling back) and nothing else
     * changes.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        $this->form->model($record)->saveRelationships();

        try {
            return app(UpdateProduct::class)($record->refresh(), ProductData::fromArray(ProductForm::withAttributeValueIds($data)));
        } catch (ProductChangeRefused|AttributeChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
