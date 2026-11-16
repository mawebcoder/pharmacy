<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Data\PurchaseItemData;
use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Support\PurchaseProgress;
use App\Modules\Pricing\Queries\SupplierOptions;
use Illuminate\Support\Facades\DB;

/**
 * Adds a row to an order's purchase document, or changes one (roadmap S30, owner 2026-11-16): this many units of an
 * order item from this supplier at this unit cost, typed by the admin. An item's rows never add up to more than it
 * was ordered, and a changed row keeps at least the units already received or marked missing. Rows change only until
 * 48 hours after the document is registered (owner, 2026-11-16).
 */
final readonly class SavePurchaseItem
{
    public function __construct(private SupplierOptions $suppliers) {}

    /**
     * @throws PurchaseRefused
     */
    public function __invoke(OrderPurchase $purchase, PurchaseItemData $data, ?OrderPurchaseItem $row = null): OrderPurchaseItem
    {
        return DB::transaction(function () use ($purchase, $data, $row): OrderPurchaseItem {
            $purchase = OrderPurchase::query()->lockForUpdate()->findOrFail($purchase->getId());
            $row = $row instanceof OrderPurchaseItem ? OrderPurchaseItem::query()->lockForUpdate()->findOrFail($row->getId()) : null;

            if ($purchase->isLocked()) {
                throw PurchaseRefused::locked();
            }
            $item = OrderItem::query()->whereKey($data->orderItemId)->where(OrderItem::COLUMN_ORDER_ID, $purchase->getOrderId())->first();

            if (! $item instanceof OrderItem) {
                throw PurchaseRefused::notThisOrder();
            }

            if ($data->quantity < 1 || $data->unitCost < 1) {
                throw PurchaseRefused::badQuantity();
            }

            $supplierName = $this->suppliers->name($data->providerId) ?? throw PurchaseRefused::unknownSupplier();

            $otherRows = (int) OrderPurchaseItem::query()
                ->where(OrderPurchaseItem::COLUMN_ORDER_PURCHASE_ID, $purchase->getId())
                ->where(OrderPurchaseItem::COLUMN_ORDER_ITEM_ID, $item->getId())
                ->when($row instanceof OrderPurchaseItem, fn ($query) => $query->whereKeyNot($row?->getId()))
                ->sum(OrderPurchaseItem::COLUMN_QUANTITY);
            $left = $item->getQuantity() - $otherRows;

            if ($data->quantity > $left) {
                throw PurchaseRefused::moreThanOrdered(max(0, $left));
            }

            $handled = $row instanceof OrderPurchaseItem ? $row->getReceivedQuantity() + $row->getMissingQuantity() : 0;

            if ($data->quantity < $handled) {
                throw PurchaseRefused::belowHandled($handled);
            }

            $row ??= (new OrderPurchaseItem)->setOrderPurchaseId($purchase->getId());
            $row->setOrderItemId($item->getId())
                ->setProviderId($data->providerId)
                ->setProviderName($supplierName)
                ->setQuantity($data->quantity)
                ->setUnitCost($data->unitCost)
                ->setExpiryDate($data->expiryDate)
                ->save();

            PurchaseProgress::refresh($purchase);

            return $row;
        });
    }
}
