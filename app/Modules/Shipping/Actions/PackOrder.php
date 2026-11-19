<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Orders\Queries\OrderPackingItems;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Models\OrderParcel;
use App\Modules\Shipping\Packing\BoxPacker;
use App\Modules\Shipping\Queries\ActiveBoxes;
use Illuminate\Support\Facades\DB;

/**
 * Packs an order into parcels and stores them on it (roadmap S31, owner 2026-11-17): runs when the order is paid and
 * again on «بسته‌بندی دوباره»; earlier parcels are replaced. Each parcel keeps its box as it was, so later box edits
 * never change a packed order.
 */
final readonly class PackOrder
{
    public function __construct(
        private OrderPackingItems $orderItems,
        private ActiveBoxes $boxes,
        private BoxPacker $packer,
    ) {}

    /**
     * @return list<OrderParcel>
     */
    public function __invoke(int $orderId): array
    {
        $items = array_map(fn (array $item): PackItem => PackItem::fromOrderItem($item), $this->orderItems->for($orderId));

        $packed = $this->packer->pack($items, $this->boxes->specs());

        return DB::transaction(function () use ($orderId, $packed): array {
            OrderParcel::query()->where(OrderParcel::COLUMN_ORDER_ID, $orderId)->delete();
            $parcels = [];

            foreach ($packed as $index => $parcel) {
                $box = $parcel->box;
                $row = (new OrderParcel)
                    ->setOrderId($orderId)
                    ->setSequence($index + 1)
                    ->setBoxId($box?->id)
                    ->setBoxSnapshot($box === null ? null : [
                        'name' => $box->name,
                        'length_cm' => $box->lengthCm,
                        'width_cm' => $box->widthCm,
                        'height_cm' => $box->heightCm,
                        'empty_weight_g' => $box->emptyWeightG,
                        'tipax_size' => $box->tipaxSize,
                    ])
                    ->setItems(array_map(fn (array $item): array => [
                        'order_item_id' => $item['key'],
                        'variant_id' => $item['variant_id'],
                        'title' => $item['title'],
                        'quantity' => $item['quantity'],
                    ], $parcel->items))
                    ->setItemsWeightG($parcel->itemsWeightG)
                    ->setTotalWeightG($parcel->totalWeightG)
                    ->setDeclaredValue($parcel->declaredValue)
                    ->setContentType($parcel->content)
                    ->setTipaxContentId($parcel->tipaxContentId)
                    ->setIsEstimate($parcel->isEstimate);
                $row->save();
                $parcels[] = $row;
            }

            return $parcels;
        });
    }
}
