<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Controllers;

use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Http\Resources\CartQuantitiesResource;
use App\Modules\Cart\Queries\CurrentCart;
use Illuminate\Http\JsonResponse;

/**
 * GET /cart/quantities (S19): how many of each SKU the shopper (customer, or guest by the encrypted cart cookie) has,
 * for the steppers on product cards. Never cached: it is per shopper.
 */
final class CartQuantitiesController
{
    public function __invoke(CurrentCart $carts): JsonResponse
    {
        return (new CartQuantitiesResource($carts->quantities(CartOwnerData::current())))
            ->response()
            ->header('Cache-Control', 'no-store, private');
    }
}
