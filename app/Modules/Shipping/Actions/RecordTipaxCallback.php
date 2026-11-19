<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Enums\TipaxShipmentStatus;
use App\Modules\Shipping\Models\TipaxShipment;

/**
 * Keeps Tipax's callback on the shipment it is about (roadmap S35): success confirms the registration, failure marks
 * it failed with Tipax's message for staff. The shipment is found by Tipax's correlation id, else by a tracking code
 * in the payload. [CONFIRM] the payload's shape with Tipax.
 */
final class RecordTipaxCallback
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(bool $succeeded, array $payload): bool
    {
        $shipment = $this->shipmentFor($payload);

        if (! $shipment instanceof TipaxShipment) {
            return false;
        }

        $shipment->setCallback($payload)
            ->setStatus($succeeded ? TipaxShipmentStatus::Confirmed : TipaxShipmentStatus::Failed)
            ->setFailureReason($succeeded ? null : (self::find($payload, ['message', 'Message', 'error']) ?? 'تیپاکس ثبت را ناموفق اعلام کرد.'))
            ->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shipmentFor(array $payload): ?TipaxShipment
    {
        $correlationId = self::find($payload, ['correlationId', 'CorrelationId', 'correlation_id']);

        if ($correlationId !== null) {
            $shipment = TipaxShipment::query()->where(TipaxShipment::COLUMN_CORRELATION_ID, $correlationId)->first();

            if ($shipment instanceof TipaxShipment) {
                return $shipment;
            }
        }

        $trackingCode = self::find($payload, ['trackingCode', 'TrackingCode']);

        return $trackingCode === null ? null : TipaxShipment::query()->whereJsonContains(TipaxShipment::COLUMN_TRACKING_CODES, $trackingCode)->first();
    }

    /**
     * The first string value under one of the keys, anywhere in the payload.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  list<string>  $keys
     */
    private static function find(array $payload, array $keys): ?string
    {
        foreach ($payload as $key => $value) {
            if (in_array($key, $keys, true) && is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }

            if (is_array($value) && ($found = self::find($value, $keys)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
