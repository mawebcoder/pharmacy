<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Data\ParcelStatusData;
use App\Modules\Shipping\Exceptions\ParcelCheckTooSoon;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Models\ParcelTracking;
use App\Modules\Shipping\Tipax\TipaxClient;

/**
 * An order's parcel status for its page (roadmap S37, owner 2026-11-19): Tipax's BriefTracking, asked at most once an
 * hour per order. Within the hour, or when Tipax fails, the last answer is shown; refreshing the page never asks again.
 * A failed ask also waits the hour, so a Tipax outage is not hammered.
 */
final readonly class ParcelTrackingStatus
{
    public function __construct(
        private TipaxClient $tipax,
        private int $refreshMinutes,
    ) {}

    public function for(int $orderId, string $trackingCode): ?ParcelStatusData
    {
        $tracking = ParcelTracking::query()->where(ParcelTracking::COLUMN_ORDER_ID, $orderId)->first();
        $isFresh = $tracking instanceof ParcelTracking
            && $tracking->getTrackingCode() === $trackingCode
            && $tracking->getCheckedAt()->isAfter(now()->subMinutes($this->refreshMinutes));

        if (! $isFresh && $this->tipax->isConfigured()) {
            $tracking = $this->refresh($tracking ?? (new ParcelTracking)->setOrderId($orderId), $trackingCode);
        }

        if (! $tracking instanceof ParcelTracking || $tracking->getData() === null || $tracking->getTrackingCode() !== $trackingCode) {
            return null;
        }

        return ParcelStatusData::fromTipax($tracking->getData(), $tracking->getSyncedAt());
    }

    /**
     * «استعلام از سرویس ارسال» on the customer's order page (owner, 2026-11-19): asks now, but only an hour after the
     * last successful answer; after a failed ask the customer may try again at once.
     *
     * @throws ParcelCheckTooSoon within the hour after a successful answer
     * @throws TipaxRequestFailed when the carrier does not answer or refuses
     */
    public function checkIfDue(int $orderId, string $trackingCode): ParcelStatusData
    {
        $tracking = ParcelTracking::query()->where(ParcelTracking::COLUMN_ORDER_ID, $orderId)->first();
        $syncedAt = $tracking instanceof ParcelTracking && $tracking->getTrackingCode() === $trackingCode ? $tracking->getSyncedAt() : null;

        if ($syncedAt !== null && $syncedAt->isAfter(now()->subMinutes($this->refreshMinutes))) {
            throw new ParcelCheckTooSoon(max(1, (int) ceil(now()->diffInMinutes($syncedAt->copy()->addMinutes($this->refreshMinutes)))));
        }

        try {
            return $this->checkNow($orderId, $trackingCode);
        } catch (TipaxRequestFailed $failed) {
            // Remember the attempt; the last answer, if any, stays on the page.
            $tracking?->setCheckedAt(now())->save();

            throw $failed;
        }
    }

    /**
     * «استعلام از تیپاکس» on the admin order page (owner, 2026-11-19): asks Tipax now, whatever the hour, and keeps the
     * answer for the customer's page too.
     *
     * @throws TipaxRequestFailed with Tipax's reason when it does not answer or refuses
     */
    public function checkNow(int $orderId, string $trackingCode): ParcelStatusData
    {
        $tracking = ParcelTracking::query()->where(ParcelTracking::COLUMN_ORDER_ID, $orderId)->first() ?? (new ParcelTracking)->setOrderId($orderId);

        if ($tracking->getTrackingCode() !== $trackingCode) {
            $tracking->setTrackingCode($trackingCode)->setStatus(null)->setData(null)->setSyncedAt(null);
        }

        $answer = $this->tipax->briefTracking($trackingCode);
        $tracking->setData($answer)
            ->setStatus(is_string($answer['contractStatusName'] ?? null) ? $answer['contractStatusName'] : null)
            ->setSyncedAt(now())
            ->setCheckedAt(now())
            ->save();

        return ParcelStatusData::fromTipax($answer, $tracking->getSyncedAt());
    }

    private function refresh(ParcelTracking $tracking, string $trackingCode): ParcelTracking
    {
        if ($tracking->getTrackingCode() !== $trackingCode) {
            // A new code (typed again by staff): the old answer belongs to another parcel.
            $tracking->setTrackingCode($trackingCode)->setStatus(null)->setData(null)->setSyncedAt(null);
        }

        try {
            $answer = $this->tipax->briefTracking($trackingCode);
            $tracking->setData($answer)
                ->setStatus(is_string($answer['contractStatusName'] ?? null) ? $answer['contractStatusName'] : null)
                ->setSyncedAt(now());
        } catch (TipaxRequestFailed) {
            // Keep the last answer; it is shown with its time.
        }

        $tracking->setCheckedAt(now())->save();

        return $tracking;
    }
}
