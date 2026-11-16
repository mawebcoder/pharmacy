<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Support\PurchaseProgress;
use Illuminate\Support\Facades\DB;

/**
 * Removes a purchase document row typed by mistake (roadmap S30); a row with units received or marked missing stays,
 * and so does every row once the document's 48-hour edit window has closed.
 */
final readonly class DeletePurchaseItem
{
    /**
     * @throws PurchaseRefused
     */
    public function __invoke(OrderPurchaseItem $row): void
    {
        DB::transaction(function () use ($row): void {
            $row = OrderPurchaseItem::query()->lockForUpdate()->findOrFail($row->getId());

            if ($row->getReceivedQuantity() > 0 || $row->getMissingQuantity() > 0) {
                throw PurchaseRefused::alreadyHandled();
            }

            $purchase = OrderPurchase::query()->findOrFail($row->getOrderPurchaseId());

            if ($purchase->isLocked()) {
                throw PurchaseRefused::locked();
            }

            $row->delete();

            PurchaseProgress::refresh($purchase);
        });
    }
}
