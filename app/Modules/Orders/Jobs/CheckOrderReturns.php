<?php

declare(strict_types=1);

namespace App\Modules\Orders\Jobs;

use App\Modules\Orders\Actions\InquireOrderReturn;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Every night with the delivery check (roadmap S27, owner 2026-11-19): each return Tipax is collecting (approved or
 * picked up) is asked about, so pickups and arrivals at the warehouse show without anyone clicking.
 */
final class CheckOrderReturns implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function handle(InquireOrderReturn $inquire): void
    {
        $returns = OrderReturn::query()
            ->where(OrderReturn::COLUMN_NEEDS_PICKUP, true)
            ->whereIn(OrderReturn::COLUMN_STATUS, [OrderReturnStatus::Approved, OrderReturnStatus::PickedUp])
            ->with('order')
            ->orderBy(OrderReturn::COLUMN_ID)
            ->lazyById();

        foreach ($returns as $return) {
            /** @var OrderReturn $return */
            try {
                $inquire($return);
            } catch (TipaxRequestFailed $failed) {
                Log::warning('Tipax tracking failed for an order return.', ['order_return_id' => $return->getId(), 'reason' => $failed->getMessage()]);
            }
        }
    }
}
