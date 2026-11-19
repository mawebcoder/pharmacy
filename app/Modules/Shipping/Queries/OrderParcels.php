<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Models\OrderParcel;
use App\Modules\Shipping\Packing\BoxPacker;

/**
 * An order's parcels for other modules (roadmap S31): the order admin page shows them.
 */
final readonly class OrderParcels
{
    /**
     * @return list<array{sequence: int, box: string|null, sizes: string|null, box_weight_g: int, items: list<array{title: string, quantity: int}>, items_weight_g: int, total_weight_g: int, content: string, is_estimate: bool}>
     */
    public function for(int $orderId): array
    {
        return array_values(OrderParcel::query()
            ->where(OrderParcel::COLUMN_ORDER_ID, $orderId)
            ->orderBy(OrderParcel::COLUMN_SEQUENCE)
            ->get()
            ->map(function (OrderParcel $parcel): array {
                $box = $parcel->getBoxSnapshot();

                return [
                    'sequence' => $parcel->getSequence(),
                    'box' => $box['name'] ?? null,
                    'sizes' => $box === null ? null : $box['length_cm'].' × '.$box['width_cm'].' × '.$box['height_cm'],
                    'box_weight_g' => (int) ($box['empty_weight_g'] ?? 0),
                    'items' => array_map(fn (array $item): array => ['title' => $item['title'], 'quantity' => $item['quantity']], $parcel->getItems()),
                    'items_weight_g' => $parcel->getItemsWeightG(),
                    'total_weight_g' => $parcel->getTotalWeightG(),
                    'content' => $parcel->getContentType()->label(),
                    'is_estimate' => $parcel->isEstimate(),
                ];
            })
            ->all());
    }

    /**
     * The order's parcels as they were packed after payment, for registering them with Tipax (S35): sizes from the box
     * as it was, weight, value and Tipax content.
     *
     * @return list<PackedParcel>
     */
    public function packed(int $orderId): array
    {
        return array_values(OrderParcel::query()
            ->where(OrderParcel::COLUMN_ORDER_ID, $orderId)
            ->orderBy(OrderParcel::COLUMN_SEQUENCE)
            ->get()
            ->map(function (OrderParcel $parcel): PackedParcel {
                $box = $parcel->getBoxSnapshot();

                return new PackedParcel(
                    box: $box === null ? null : new BoxSpec(
                        id: (int) $parcel->getBoxId(),
                        name: $box['name'],
                        lengthCm: $box['length_cm'],
                        widthCm: $box['width_cm'],
                        heightCm: $box['height_cm'],
                        emptyWeightG: $box['empty_weight_g'],
                        maxWeightG: BoxPacker::MAX_PARCEL_WEIGHT_G,
                        tipaxSize: isset($box['tipax_size']) ? (int) $box['tipax_size'] : null,
                    ),
                    items: [],
                    itemsWeightG: $parcel->getItemsWeightG(),
                    totalWeightG: $parcel->getTotalWeightG(),
                    declaredValue: $parcel->getDeclaredValue(),
                    content: $parcel->getContentType(),
                    isEstimate: $parcel->isEstimate(),
                    tipaxContentId: $parcel->getTipaxContentId(),
                );
            })
            ->all());
    }
}
