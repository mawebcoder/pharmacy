<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

use App\Modules\Shipping\Enums\TipaxShipmentKind;

/**
 * An order to register with Tipax (roadmap S35, owner 2026-11-19): out to the customer, or back to the warehouse as a
 * return; who sends and receives, its packed parcels and the Tipax service.
 */
final readonly class TipaxRegistrationData
{
    /**
     * @param  list<PackedParcel>  $parcels
     */
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public TipaxShipmentKind $kind,
        public TipaxPartyData $sender,
        public TipaxPartyData $receiver,
        public array $parcels,
        public ?int $serviceId,
    ) {}

    /**
     * Our reference on Tipax's side: the order number, with «-R» for a return.
     */
    public function traceCode(): string
    {
        return $this->kind === TipaxShipmentKind::Return ? $this->orderNumber.'-R' : $this->orderNumber;
    }
}
