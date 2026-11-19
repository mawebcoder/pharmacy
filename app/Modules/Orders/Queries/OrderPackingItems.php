<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Models\OrderItem;

/**
 * An order's items as Shipping packs them (roadmap S31): read from each item's snapshot of the SKU when the order was
 * placed (weight, how many fit each box, «ارسال جداگانه»; the product's liquid, fragile and Tipax content, which were
 * the SKU's before 2026-11-19), never from the SKU now. An item whose
 * snapshot has no boxes fits none and is left for staff.
 */
final readonly class OrderPackingItems
{
    /**
     * @return list<array{order_item_id: int, variant_id: int|null, title: string, quantity: int, weight_g: int|null, box_capacities: array<int, int>, unit_value: int, ship_separately: bool, liquid_or_fragile: bool, tipax_content_id: int|null}>
     */
    public function for(int $orderId): array
    {
        $items = [];

        foreach (OrderItem::query()->where(OrderItem::COLUMN_ORDER_ID, $orderId)->orderBy(OrderItem::COLUMN_ID)->get() as $item) {
            /** @var array<string, mixed> $sku */
            $sku = (array) ($item->getProductSnapshot()['sku'] ?? []);
            // Liquid and fragile are the product's since 2026-11-19; older orders kept them on the SKU.
            /** @var array<string, mixed> $product */
            $product = (array) ($item->getProductSnapshot()['product'] ?? []);
            $handling = array_key_exists('is_liquid', $product) ? $product : $sku;
            $weight = $sku['weight_g'] ?? null;
            $capacities = [];

            foreach ((array) ($sku['box_capacities'] ?? []) as $boxId => $count) {
                if ((int) $count > 0) {
                    $capacities[(int) $boxId] = (int) $count;
                }
            }

            $items[] = [
                'order_item_id' => $item->getId(),
                'variant_id' => $item->getVariantId(),
                'title' => trim($item->getProductName().' '.($item->getVariantTitle() ?? '')),
                'quantity' => $item->getQuantity(),
                'weight_g' => is_numeric($weight) && (int) $weight > 0 ? (int) $weight : null,
                'box_capacities' => $capacities,
                'unit_value' => $item->getPaidUnit(),
                'ship_separately' => (bool) ($sku['ship_separately'] ?? false),
                'liquid_or_fragile' => (bool) ($handling['is_liquid'] ?? false) || (bool) ($handling['is_fragile'] ?? false),
                'tipax_content_id' => is_numeric($product['tipax_content_id'] ?? null) ? (int) $product['tipax_content_id'] : null,
            ];
        }

        return $items;
    }
}
