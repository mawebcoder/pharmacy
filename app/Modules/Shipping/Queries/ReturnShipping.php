<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Orders\Queries\OrderPackingItems;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;
use App\Modules\Shipping\Packing\BoxPacker;
use App\Modules\Shipping\Pricing\Strategies\TipaxShippingPrice;

/**
 * The way back of an order return (roadmap S27, owner 2026-11-19): only the units really coming back are boxed, and
 * Tipax prices collecting them from the customer and delivering them to the warehouse. The price is Tipax's own (no
 * store markup); the store pays it.
 */
final readonly class ReturnShipping
{
    public function __construct(
        private OrderPackingItems $orderItems,
        private ActiveBoxes $boxes,
        private BoxPacker $packer,
        private TipaxShippingPrice $tipax,
    ) {}

    /**
     * @param  array<int, int>  $quantities  units coming back per order item id
     * @return list<PackedParcel>
     */
    public function parcels(int $orderId, array $quantities): array
    {
        $items = [];

        foreach ($this->orderItems->for($orderId) as $item) {
            $quantity = min($quantities[$item['order_item_id']] ?? 0, $item['quantity']);

            if ($quantity > 0) {
                $items[] = PackItem::fromOrderItem($item, $quantity);
            }
        }

        return $items === [] ? [] : $this->packer->pack($items, $this->boxes->specs());
    }

    /**
     * Tipax's price for the parcels: the order's own service when Tipax offers it on the way back, else the cheapest;
     * null when Tipax cannot price them.
     *
     * @param  list<PackedParcel>  $parcels
     */
    public function price(int $customerCityId, ?int $warehouseCityId, array $parcels, ?int $preferredServiceId): ?ShippingOptionData
    {
        $request = new ShippingPriceRequest($customerCityId, (int) $warehouseCityId, $parcels, isReturn: true);

        if ($warehouseCityId === null || ! $this->tipax->supports($request)) {
            return null;
        }

        try {
            $options = $this->tipax->options($request);
        } catch (ShippingPriceUnavailable) {
            return null;
        }

        usort($options, fn (ShippingOptionData $a, ShippingOptionData $b): int => $a->price <=> $b->price);

        foreach ($options as $option) {
            if (($option->details['service_id'] ?? null) === $preferredServiceId) {
                return $option;
            }
        }

        return $options[0] ?? null;
    }
}
