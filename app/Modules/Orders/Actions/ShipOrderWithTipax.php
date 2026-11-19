<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\TipaxParties;
use App\Modules\Shipping\Actions\RegisterTipaxShipment;
use App\Modules\Shipping\Data\TipaxRegistrationData;
use App\Modules\Shipping\Enums\TipaxShipmentKind;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\OrderParcels;

/**
 * «تحویل به حمل» with Tipax (roadmap S35, owner 2026-11-19): registers the order's packed parcels with Tipax, from the
 * warehouse to the customer with the service priced at checkout, then hands the order to the carrier with Tipax's
 * first tracking code (the customer's SMS follows). A refusal leaves the order as it is.
 */
final readonly class ShipOrderWithTipax
{
    public function __construct(
        private TipaxParties $parties,
        private OrderParcels $parcels,
        private RegisterTipaxShipment $register,
        private SendOrderOnItsWay $sendOnItsWay,
    ) {}

    /**
     * @return list<string> Tipax's tracking codes, one per parcel
     *
     * @throws OrderRefused
     */
    public function __invoke(Order $order, int $adminId): array
    {
        $parcels = $this->parcels->packed($order->getId());

        if ($parcels === []) {
            throw OrderRefused::notPacked();
        }

        $serviceId = $order->getShippingQuoteSnapshot()['details']['service_id'] ?? null;

        try {
            $codes = ($this->register)(new TipaxRegistrationData(
                orderId: $order->getId(),
                orderNumber: (string) $order->getNumber(),
                kind: TipaxShipmentKind::Outbound,
                sender: $this->parties->warehouse(),
                receiver: $this->parties->customer($order),
                parcels: $parcels,
                serviceId: is_numeric($serviceId) ? (int) $serviceId : null,
            ));
        } catch (TipaxRequestFailed $failed) {
            throw OrderRefused::carrierRefused($failed->getMessage());
        }

        ($this->sendOnItsWay)($order, $codes[0], $adminId);

        return $codes;
    }
}
