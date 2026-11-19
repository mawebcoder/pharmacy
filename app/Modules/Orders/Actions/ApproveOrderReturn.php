<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Events\OrderReturnRegistered;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Support\TipaxParties;
use App\Modules\Shipping\Actions\RegisterTipaxShipment;
use App\Modules\Shipping\Data\TipaxRegistrationData;
use App\Modules\Shipping\Enums\TipaxShipmentKind;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\ReturnShipping;

/**
 * «تأیید» on a draft return (roadmap S27, owner 2026-11-19): registers its parcels with Tipax as an order of their own,
 * collected from the customer and delivered to the warehouse (Tipax's ReturnRegisterOrder needs Tipax's internal ids
 * we do not get), traced as the return's number (PH-…-R2). The customer is texted the return code. When Tipax refuses,
 * the return stays a draft.
 */
final readonly class ApproveOrderReturn
{
    public function __construct(
        private TipaxParties $parties,
        private ReturnShipping $shipping,
        private RegisterTipaxShipment $register,
    ) {}

    /**
     * @return list<string> the return's tracking codes
     *
     * @throws OrderRefused
     */
    public function __invoke(OrderReturn $return, ?int $adminId): array
    {
        if ($return->getStatus() !== OrderReturnStatus::Draft) {
            throw OrderRefused::returnNotDraft();
        }

        $order = $return->getOrder();
        $quantities = [];

        foreach ($return->items as $item) {
            $quantities[$item->getOrderItemId()] = $item->getQuantity();
        }

        $serviceId = $return->getReturnServiceId() ?? $order->getShippingQuoteSnapshot()['details']['service_id'] ?? null;

        try {
            $codes = ($this->register)(new TipaxRegistrationData(
                orderId: $order->getId(),
                orderNumber: (string) $order->getNumber(),
                kind: TipaxShipmentKind::Return,
                sender: $this->parties->customer($order),
                receiver: $this->parties->warehouse(),
                parcels: $this->shipping->parcels($order->getId(), $quantities),
                serviceId: is_numeric($serviceId) ? (int) $serviceId : null,
                returnSequence: $return->getSequence(),
            ));
        } catch (TipaxRequestFailed $failed) {
            throw OrderRefused::carrierRefused($failed->getMessage());
        }

        $return->setStatus(OrderReturnStatus::Approved)
            ->setTrackingCodes($codes)
            ->setApprovedBy($adminId)
            ->setApprovedAt(now())
            ->save();

        OrderReturnRegistered::dispatch($order->getId(), $codes[0]);

        return $codes;
    }
}
