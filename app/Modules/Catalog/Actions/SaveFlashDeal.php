<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\FlashDealData;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Catalog\Jobs\SyncFlashDeals;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Queries\SupplierOptions;
use Illuminate\Support\Facades\DB;

/**
 * Creates or changes a flash deal (roadmap S33): the deal price must be below the SKU's regular price (sale price
 * when set, else the price), a SKU has at most one active deal at a time, and the unit limit never drops below what
 * has been sold. The admin always chooses the supplier, which must carry the SKU (owner, 2026-11-17: for deals the
 * supplier is never inherited). The SKU's price follows in the background (SyncFlashDeals).
 */
final readonly class SaveFlashDeal
{
    public function __construct(private SupplierOptions $suppliers) {}

    /**
     * @throws FlashDealRefused
     */
    public function __invoke(FlashDealData $data, ?FlashDeal $deal = null): FlashDeal
    {
        // A deal moved to another SKU clears the old one's price too.
        $previousVariantId = $deal?->getVariantId();

        $saved = DB::transaction(function () use ($data, $deal): FlashDeal {
            $deal = $deal instanceof FlashDeal ? FlashDeal::query()->lockForUpdate()->findOrFail($deal->getId()) : new FlashDeal;
            $variant = ProductVariant::query()->findOrFail($data->variantId);

            if ($data->dealPrice >= $variant->regularPrice()) {
                throw FlashDealRefused::notCheaper($variant->regularPrice());
            }

            if ($data->providerId === null) {
                throw FlashDealRefused::supplierRequired();
            }

            if ($this->suppliers->cost($data->providerId, $data->variantId) === null) {
                throw FlashDealRefused::supplierLacksSku();
            }

            if ($data->quantityLimit !== null && $data->quantityLimit < $deal->getSoldCount()) {
                throw FlashDealRefused::belowSold($deal->getSoldCount());
            }

            $overlapping = $data->isActive && FlashDeal::query()
                ->where(FlashDeal::COLUMN_VARIANT_ID, $data->variantId)
                ->where(FlashDeal::COLUMN_IS_ACTIVE, true)
                ->when($deal->exists, fn ($query) => $query->whereKeyNot($deal->getId()))
                ->where(FlashDeal::COLUMN_STARTS_AT, '<', $data->endsAt)
                ->where(FlashDeal::COLUMN_ENDS_AT, '>', $data->startsAt)
                ->exists();

            if ($overlapping) {
                throw FlashDealRefused::overlaps();
            }

            $deal->setVariantId($data->variantId)
                ->setProviderId($data->providerId)
                ->setDealPrice($data->dealPrice)
                ->setStartsAt($data->startsAt)
                ->setEndsAt($data->endsAt)
                ->setQuantityLimit($data->quantityLimit)
                ->setIsActive($data->isActive)
                ->save();

            return $deal;
        });

        SyncFlashDeals::dispatch(array_values(array_unique(array_filter([$saved->getVariantId(), $previousVariantId]))))->afterCommit();

        return $saved;
    }
}
