<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

/**
 * The ways to ship a cart, priced (roadmap S34), with the parcels they were priced from, so checkout can show the
 * packing outside production (owner, 2026-11-18).
 */
final readonly class ShippingQuoteData
{
    /**
     * @param  list<ShippingOptionData>  $options  cheapest first, never empty
     * @param  list<PackedParcel>  $parcels  empty when nothing was packed (no address yet)
     */
    public function __construct(
        public array $options,
        public array $parcels,
    ) {}

    public function cheapest(): ShippingOptionData
    {
        return $this->options[0];
    }
}
