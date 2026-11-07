<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductAttributes;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product. A new product has no variants or photos yet, so it is always saved as a draft unless it can
 * pass the activation checks (ProductActivation).
 */
final class CreateProduct
{
    /**
     * @throws ProductChangeRefused
     * @throws AttributeChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(ProductData $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            ProductAttributes::assertValid($data->attributeValueIds);

            $product = UpdateProduct::fill(new Product, $data)->setStatus(ProductStatus::Draft);
            $product->save();
            UpdateProduct::syncLinks($product, $data);

            if ($data->status !== ProductStatus::Draft) {
                return app(UpdateProduct::class)($product, $data);
            }

            return $product;
        });
    }
}
