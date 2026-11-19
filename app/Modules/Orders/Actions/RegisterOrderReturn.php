<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderReturnRegistered;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\TipaxParties;
use App\Modules\Shipping\Actions\PackOrder;
use App\Modules\Shipping\Actions\RegisterTipaxShipment;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\TipaxRegistrationData;
use App\Modules\Shipping\Enums\TipaxShipmentKind;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\OrderParcels;
use App\Modules\Shipping\Queries\TipaxShipments;

/**
 * «ثبت مرجوعی در تیپاکس» on a cancelled order that went out with Tipax, and «ثبت مرجوعی از مشتری» on a delivered one
 * the customer sends back (roadmap S27/S35, owner 2026-11-19): registers
 * the parcels coming back, collected from the customer and delivered to the warehouse, as a Tipax order of its own
 * (Tipax's ReturnRegisterOrder needs Tipax's internal ids we do not get). The customer is texted the return code.
 */
final readonly class RegisterOrderReturn
{
    public function __construct(
        private TipaxParties $parties,
        private OrderParcels $parcels,
        private TipaxShipments $shipments,
        private RegisterTipaxShipment $register,
        private PackOrder $pack,
    ) {}

    /**
     * A cancelled order that went out (registered with Tipax, or given a tracking code by hand), or a delivered one the
     * customer sends back, before its return is registered.
     */
    public function applies(Order $order): bool
    {
        $wentOut = match ($order->getStatus()) {
            OrderStatus::Delivered => true,
            OrderStatus::Cancelled => $order->getTrackingCode() !== null || $this->shipments->wasSent($order->getId()),
            default => false,
        };

        return $wentOut && ! $this->shipments->hasReturn($order->getId());
    }

    /**
     * @return list<string> the return's tracking codes
     *
     * @throws OrderRefused
     */
    public function __invoke(Order $order): array
    {
        if (! $this->applies($order)) {
            throw OrderRefused::notReturnable();
        }

        $serviceId = $order->getShippingQuoteSnapshot()['details']['service_id'] ?? null;

        try {
            $codes = ($this->register)(new TipaxRegistrationData(
                orderId: $order->getId(),
                orderNumber: (string) $order->getNumber(),
                kind: TipaxShipmentKind::Return,
                sender: $this->parties->customer($order),
                receiver: $this->parties->warehouse(),
                parcels: $this->parcelsOf($order->getId()),
                serviceId: is_numeric($serviceId) ? (int) $serviceId : null,
            ));
        } catch (TipaxRequestFailed $failed) {
            throw OrderRefused::carrierRefused($failed->getMessage());
        }

        OrderReturnRegistered::dispatch($order->getId(), $codes[0]);

        return $codes;
    }

    /**
     * The order's parcels; an order shipped before packing was stored (e.g. with a hand-written code) is packed first.
     *
     * @return list<PackedParcel>
     */
    private function parcelsOf(int $orderId): array
    {
        $parcels = $this->parcels->packed($orderId);

        if ($parcels === []) {
            ($this->pack)($orderId);
            $parcels = $this->parcels->packed($orderId);
        }

        return $parcels;
    }
}
