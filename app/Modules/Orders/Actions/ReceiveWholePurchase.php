<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Support\PurchaseProgress;
use Illuminate\Support\Facades\DB;

/**
 * «دریافت کامل همه ردیف‌ها» (roadmap S30): every row of the document arrived in full; any missing marks are cleared.
 */
final readonly class ReceiveWholePurchase
{
    public function __invoke(OrderPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $purchase = OrderPurchase::query()->lockForUpdate()->findOrFail($purchase->getId());

            foreach ($purchase->items as $row) {
                /** @var OrderPurchaseItem $row */
                $row->setReceivedQuantity($row->getQuantity())->setMissingQuantity(0)->setMissingNote(null)->save();
            }

            PurchaseProgress::refresh($purchase);
        });
    }
}
