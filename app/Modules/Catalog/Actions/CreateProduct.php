<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product. A new product has no variants or photos yet, so it is always saved as a draft unless it can
 * pass the activation checks (ProductActivation).
 */
final class CreateProduct
{
    /**
     * @throws ProductChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(ProductData $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = UpdateProduct::fill(new Product, $data)->setStatus(ProductStatus::Draft);
            $product->save();
            $product->categories()->sync($data->categoryIds);

            if ($data->status !== ProductStatus::Draft) {
                return app(UpdateProduct::class)($product, $data);
            }

            return $product;
        });
    }
}
