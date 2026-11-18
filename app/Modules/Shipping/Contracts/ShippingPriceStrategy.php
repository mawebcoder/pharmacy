<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Contracts;

use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;

/**
 * One shipping service's way of pricing an order (roadmap S34, strategy pattern; owner 2026-11-18). Each service
 * (Tipax now, the own courier later) is one strategy; ShippingPriceCalculator asks every available one and offers
 * their options together. A strategy turns our request into its service's request and the answer back into our
 * options through its own adapters, so no service's shapes leak out of it.
 */
interface ShippingPriceStrategy
{
    /**
     * A short name, unique among the strategies (e.g. «tipax»), used in option keys and cache keys.
     */
    public function key(): string;

    /**
     * Whether this service can price the request at all (turned on, configured, serves the route).
     */
    public function supports(ShippingPriceRequest $request): bool;

    /**
     * The service's options for the request, in rial; empty when it offers none on this route.
     *
     * @return list<ShippingOptionData>
     *
     * @throws ShippingPriceUnavailable when the service cannot be asked or answers with an error
     */
    public function options(ShippingPriceRequest $request): array;
}
