<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `{ "data": { "items": { "<variant id>": <quantity> }, "count": <total units> } }` for the product cards (S19).
 *
 * @property-read array<int, int> $resource variant id => quantity
 */
final class CartQuantitiesResource extends JsonResource
{
    /**
     * @return array{items: object, count: int}
     */
    public function toArray(Request $request): array
    {
        /** @var array<int, int> $quantities */
        $quantities = $this->resource;

        return [
            // An object even when empty, so clients always read a map.
            'items' => (object) $quantities,
            'count' => array_sum($quantities),
        ];
    }
}
