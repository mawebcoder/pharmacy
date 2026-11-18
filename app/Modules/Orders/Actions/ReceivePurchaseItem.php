<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Support\PurchaseProgress;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت دریافت» on a purchase document row (roadmap S25, D13.6): how many units arrived and how many are missing, with
 * a note for the missing ones (wait, substitute or refund is the admin's call). Together at most the row's quantity.
 * The order is ready to pack once every row of a fully covered document has arrived.
 */
final readonly class ReceivePurchaseItem
{
    /**
     * @throws PurchaseRefused
     */
    public function __invoke(OrderPurchaseItem $row, int $received, int $missing = 0, ?string $missingNote = null): void
    {
        DB::transaction(function () use ($row, $received, $missing, $missingNote): void {
            $row = OrderPurchaseItem::query()->lockForUpdate()->findOrFail($row->getId());

            if ($received < 0 || $missing < 0 || $received + $missing > $row->getQuantity()) {
                throw PurchaseRefused::receivedTooMany();
            }

            $note = $missingNote === null ? null : trim($missingNote);

            $row->setReceivedQuantity($received)
                ->setMissingQuantity($missing)
                ->setMissingNote($missing > 0 && $note !== '' ? $note : null)
                ->save();

            PurchaseProgress::refresh(OrderPurchase::query()->findOrFail($row->getOrderPurchaseId()));
        });
    }
}
