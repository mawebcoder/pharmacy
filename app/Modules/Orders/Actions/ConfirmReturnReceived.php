<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\OrderReturn;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت دریافت در انبار» (roadmap S27, owner 2026-11-19): once Tipax reports the return delivered to us, staff count what
 * arrived, row by row (Tipax reports parcels, not items), and mark the return received. Only the units received are refunded; the
 * refund can be recorded from then on.
 */
final readonly class ConfirmReturnReceived
{
    public function __construct(
        private RecalculateReturnRefunds $recalculate,
        private OpenReturnRefund $openRefund,
    ) {}

    /**
     * @param  array<int, int>  $received  units received per return row id
     *
     * @throws OrderRefused
     */
    public function __invoke(OrderReturn $return, array $received, ?int $adminId): void
    {
        DB::transaction(function () use ($return, $received, $adminId): void {
            $return = OrderReturn::query()->lockForUpdate()->findOrFail($return->getId());

            if (! $return->isReceivable()) {
                throw OrderRefused::returnNotReceivable();
            }

            foreach ($return->items as $row) {
                $units = (int) ($received[$row->getId()] ?? 0);

                if ($units < 0 || $units > $row->getQuantity()) {
                    throw OrderRefused::returnReceivedTooMany(trim($row->getOrderItem()->getProductName().' '.($row->getOrderItem()->getVariantTitle() ?? '')), $row->getQuantity());
                }

                $row->setReceivedQuantity($units)->save();
            }

            $return->setStatus(OrderReturnStatus::Received)->setReceivedAt(now())->save();

            ($this->recalculate)($return->getOrder());
            // The refund record opens in «بازگشت وجه‌ها», waiting for approval (owner, 2026-11-19).
            ($this->openRefund)($return->refresh(), $adminId);
        });

        $return->refresh();
    }
}
