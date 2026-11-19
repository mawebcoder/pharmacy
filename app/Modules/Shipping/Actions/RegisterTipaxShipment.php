<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Data\TipaxRegistrationData;
use App\Modules\Shipping\Enums\TipaxShipmentStatus;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Models\TipaxShipment;
use App\Modules\Shipping\Tipax\Adapters\TipaxOrderRequestAdapter;
use App\Modules\Shipping\Tipax\TipaxClient;

/**
 * Registers an order's parcels with Tipax, going out or coming back (roadmap S35, owner 2026-11-19), and keeps the
 * answer in tipax_shipments. Once registered, asking again returns the same tracking codes instead of registering
 * twice; a failed registration may be tried again. Tipax's callbacks later confirm or fail it.
 */
final readonly class RegisterTipaxShipment
{
    public function __construct(
        private TipaxClient $tipax,
        private TipaxOrderRequestAdapter $adapter,
    ) {}

    /**
     * @return list<string> the tracking codes, one per parcel
     *
     * @throws TipaxRequestFailed with Tipax's reason when it refuses or cannot be reached
     */
    public function __invoke(TipaxRegistrationData $registration): array
    {
        $shipment = TipaxShipment::query()
            ->where(TipaxShipment::COLUMN_ORDER_ID, $registration->orderId)
            ->where(TipaxShipment::COLUMN_KIND, $registration->kind)
            ->first();

        if ($shipment instanceof TipaxShipment && $shipment->getStatus() !== TipaxShipmentStatus::Failed) {
            return $shipment->getTrackingCodes();
        }

        $secret = (string) config('shipping.tipax.webhook_secret');
        $body = $this->adapter->toBody(
            $registration,
            route('shipping.tipax.callback', ['result' => 'success', 'secret' => $secret]),
            route('shipping.tipax.callback', ['result' => 'failure', 'secret' => $secret]),
        );
        $shipment ??= (new TipaxShipment)->setOrderId($registration->orderId)->setKind($registration->kind);
        $shipment->setRequest($body);

        try {
            $answer = $this->tipax->registerOrder($body);
        } catch (TipaxRequestFailed $failed) {
            $shipment->setStatus(TipaxShipmentStatus::Failed)->setFailureReason($failed->getMessage())->save();

            throw $failed;
        }

        $shipment->setStatus(TipaxShipmentStatus::Registered)
            ->setCorrelationId($answer['correlation_id'])
            ->setTrackingCodes($answer['tracking_codes'])
            ->setResponse($answer['raw'])
            ->setFailureReason(null)
            ->setRegisteredAt(now())
            ->save();

        return $answer['tracking_codes'];
    }
}
