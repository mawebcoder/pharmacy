<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Pages;

use App\Modules\Catalog\Actions\CreateProduct as CreateProductAction;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Filament\Resources\Products\Schemas\ProductForm;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * A new product starts as a draft with its first SKU; more variants and photos are added on the edit page, then it can
 * be activated.
 */
final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var array<string, mixed> $firstVariant */
        $firstVariant = $data[ProductForm::FIRST_VARIANT] ?? [];
        unset($data[ProductForm::FIRST_VARIANT]);

        try {
            return app(CreateProductAction::class)(
                ProductData::fromArray([...ProductForm::withAttributeValueIds($data), 'status' => ProductStatus::Draft]),
                ProductVariantData::fromArray($firstVariant),
            );
        } catch (AttributeChangeRefused|ProductChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
