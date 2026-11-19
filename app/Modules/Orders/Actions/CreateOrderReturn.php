<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnItem;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Queries\ReturnShipping;
use Illuminate\Support\Facades\DB;

/**
 * Opens an order return as a draft (roadmap S27, owner 2026-11-19): the units coming back (at most what no open return
 * holds), what the customer paid per unit, the refund, and Tipax's price for collecting only those units from the
 * customer (boxed again). Nothing is sent to Tipax until the return is approved.
 */
final readonly class CreateOrderReturn
{
    public function __construct(
        private OrderReturns $returns,
        private ReturnShipping $shipping,
        private StoreSettings $settings,
        private RecalculateReturnRefunds $recalculate,
    ) {}

    /**
     * @param  array<int, int>  $quantities  units per order item id
     *
     * @throws OrderRefused
     */
    public function __invoke(Order $order, array $quantities, ?string $reason, ?int $adminId): OrderReturn
    {
        $order->loadMissing('items');
        $quantities = array_filter(array_map(intval(...), $quantities), fn (int $quantity): bool => $quantity > 0);

        if (! $this->returns->canReturn($order)) {
            throw OrderRefused::notReturnable();
        }

        if ($quantities === []) {
            throw OrderRefused::returnEmpty();
        }

        $remaining = $this->returns->remaining($order);

        foreach ($quantities as $orderItemId => $quantity) {
            $item = $order->items->first(fn ($item): bool => $item->getId() === $orderItemId);

            if ($item === null || $quantity > ($remaining[$orderItemId] ?? 0)) {
                throw OrderRefused::returnTooMany(trim(($item?->getProductName() ?? '').' '.($item?->getVariantTitle() ?? '')), $remaining[$orderItemId] ?? 0);
            }
        }

        $parcels = $this->shipping->parcels($order->getId(), $quantities);
        $serviceId = $order->getShippingQuoteSnapshot()['details']['service_id'] ?? null;
        $price = $this->shipping->price(
            (int) ($order->getAddressSnapshot()['city_id'] ?? 0),
            $this->settings->shippingOriginCityId(),
            $parcels,
            is_numeric($serviceId) ? (int) $serviceId : null,
        );

        $return = DB::transaction(function () use ($order, $quantities, $reason, $adminId, $parcels, $price): OrderReturn {
            $sequence = (int) OrderReturn::query()->where(OrderReturn::COLUMN_ORDER_ID, $order->getId())->lockForUpdate()->max(OrderReturn::COLUMN_SEQUENCE) + 1;
            $reason = trim((string) $reason);

            $return = (new OrderReturn)
                ->setOrderId($order->getId())
                ->setSequence($sequence)
                ->setStatus(OrderReturnStatus::Draft)
                ->setIsFull(OrderReturns::coversAll($order, $quantities))
                ->setReason($reason === '' ? null : $reason)
                ->setReturnShippingPrice($price?->price)
                ->setReturnServiceId(is_numeric($price?->details['service_id'] ?? null) ? (int) $price->details['service_id'] : null)
                ->setReturnServiceTitle($price?->title)
                ->setParcels(array_map(fn (PackedParcel $parcel): array => [
                    'box' => $parcel->box?->name,
                    'weight_g' => $parcel->totalWeightG,
                    'value' => $parcel->declaredValue,
                ], $parcels))
                ->setCreatedBy($adminId);
            $return->save();

            foreach ($order->items as $item) {
                if (isset($quantities[$item->getId()])) {
                    (new OrderReturnItem)
                        ->setOrderReturnId($return->getId())
                        ->setOrderItemId($item->getId())
                        ->setQuantity($quantities[$item->getId()])
                        ->setPaidUnit($item->getPaidUnit())
                        ->save();
                }
            }

            ($this->recalculate)($order);

            return $return;
        });

        return $return->refresh();
    }
}
