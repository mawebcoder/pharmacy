<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\ProviderPriceEvent;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\ProviderPriceChange;
use Illuminate\Support\Facades\DB;

/**
 * Adds or changes a supplier's price of a SKU: cost, «موجود نزد تأمین‌کننده», «فروش از این تأمین‌کننده», the SKU ×
 * supplier commission, «تأمین‌کننده انتخابی» (one per SKU) and lead time. Every change is kept in provider_price_changes; the SKU is re-priced in the background (the active supplier
 * may switch).
 */
final class SaveProviderPrice
{
    #[\NoDiscard]
    public function __invoke(ProviderPriceData $data, ?ProviderPrice $price = null, ?int $adminId = null, PriceChangeReason $reason = PriceChangeReason::ProviderPriceChanged): ProviderPrice
    {
        return DB::transaction(function () use ($data, $price, $adminId, $reason): ProviderPrice {
            $price ??= new ProviderPrice;
            $event = $price->exists ? ProviderPriceEvent::Updated : ProviderPriceEvent::Created;

            $price->setProviderId($data->providerId)
                ->setVariantId($data->variantId)
                ->setCost($data->cost)
                ->setIsAvailable($data->isAvailable)
                ->setIsEnabled($data->isEnabled)
                ->setIsPreferred($data->isPreferred)
                ->setCommissionPercent($data->commissionPercent)
                ->setLeadTimeHours($data->leadTimeHours);

            if ($price->exists && ! $price->isDirty()) {
                return $price;
            }

            $price->save();
            self::recordChange($price, $event, $adminId);

            // One preferred supplier per SKU: choosing this one clears the others.
            if ($price->isPreferred()) {
                $others = ProviderPrice::query()
                    ->where(ProviderPrice::COLUMN_VARIANT_ID, $price->getVariantId())
                    ->whereKeyNot($price->getId())
                    ->where(ProviderPrice::COLUMN_IS_PREFERRED, true)
                    ->get();

                foreach ($others as $other) {
                    $other->setIsPreferred(false)->save();
                    self::recordChange($other, ProviderPriceEvent::Updated, $adminId);
                }
            }
            RecalculateVariantPrices::dispatch([$price->getVariantId()], $reason, $adminId)->afterCommit();

            return $price;
        });
    }

    public static function recordChange(ProviderPrice $price, ProviderPriceEvent $event, ?int $adminId): void
    {
        (new ProviderPriceChange)
            ->setProviderId($price->getProviderId())
            ->setVariantId($price->getVariantId())
            ->setCost($event === ProviderPriceEvent::Deleted ? null : $price->getCost())
            ->setIsAvailable($event === ProviderPriceEvent::Deleted ? null : $price->isAvailable())
            ->setIsEnabled($event === ProviderPriceEvent::Deleted ? null : $price->isEnabled())
            ->setLeadTimeHours($event === ProviderPriceEvent::Deleted ? null : $price->getLeadTimeHours())
            ->setEvent($event)
            ->setChangedBy($adminId)
            ->save();
    }
}
