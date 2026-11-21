<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Data\ReturnRowData;
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
 * Opens an order return as a draft (roadmap S27, owner 2026-11-19): rows of units coming back, each with its reason (an
 * item may be on several rows; together at most what no open return holds), what the customer paid per unit, the refund, and, when the items had gone out, Tipax's price for collecting
 * only those units from the customer (boxed again). Nothing is sent to Tipax until the return is approved. Items never
 * sent need no pickup.
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
     * @param  list<ReturnRowData>  $rows  units and reason per row; an item may be on several rows
     *
     * @throws OrderRefused
     */
    public function __invoke(Order $order, array $rows, ?string $reason, ?int $adminId, bool $byCustomer = false): OrderReturn
    {
        $order->loadMissing('items');
        $rows = array_values(array_filter($rows, fn (ReturnRowData $row): bool => $row->quantity > 0));
        $quantities = ReturnRowData::quantities($rows);

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

        // Items never sent stay with us: nothing to box, price or collect (owner, 2026-11-19).
        $needsPickup = $this->returns->wentOut($order);
        $parcels = $needsPickup ? $this->shipping->parcels($order->getId(), $quantities) : [];
        $serviceId = $order->getShippingQuoteSnapshot()['details']['service_id'] ?? null;
        $price = $needsPickup ? $this->shipping->price(
            (int) ($order->getAddressSnapshot()['city_id'] ?? 0),
            $this->settings->shippingOriginCityId(),
            $parcels,
            is_numeric($serviceId) ? (int) $serviceId : null,
        ) : null;

        $return = DB::transaction(function () use ($order, $rows, $quantities, $reason, $adminId, $parcels, $price, $needsPickup, $byCustomer): OrderReturn {
            $sequence = (int) OrderReturn::query()->where(OrderReturn::COLUMN_ORDER_ID, $order->getId())->lockForUpdate()->max(OrderReturn::COLUMN_SEQUENCE) + 1;
            $reason = trim((string) $reason);

            $return = (new OrderReturn)
                ->setOrderId($order->getId())
                ->setSequence($sequence)
                ->setStatus(OrderReturnStatus::Draft)
                ->setIsFull(OrderReturns::coversAll($order, $quantities))
                ->setNeedsPickup($needsPickup)
                ->setRequestedByCustomer($byCustomer)
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

            $items = $order->items->keyBy(fn ($item): int => $item->getId());

            foreach ($rows as $row) {
                (new OrderReturnItem)
                    ->setOrderReturnId($return->getId())
                    ->setOrderItemId($row->orderItemId)
                    ->setQuantity($row->quantity)
                    ->setReason($row->reason)
                    ->setReasonNote($row->note)
                    ->setPaidUnit((int) $items->get($row->orderItemId)?->getPaidUnit())
                    ->save();
            }

            ($this->recalculate)($order);

            return $return;
        });

        return $return->refresh();
    }
}
