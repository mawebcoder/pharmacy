<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Jobs;

use App\Modules\Catalog\Actions\ApplyVariantPrices;
use App\Modules\Catalog\Data\VariantPriceUpdate;
use App\Modules\Catalog\Data\VariantPricingInput;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Actions\CalculateVariantPrice;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Queries\SupplierPrices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Re-prices SKUs in the background on the «pricing» queue (roadmap D3.2, D9): picks each SKU's active supplier,
 * runs the engine, writes a new price record when something changed, and copies the result onto the SKU with the
 * stock that follows: in stock when sold from an active supplier or at the printed price, out of stock when there
 * is neither. The pricing supervisor runs one process, so a SKU's records stay in order.
 */
final class RecalculateVariantPrices implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'pricing';

    private const int CHUNK = 200;

    public int $tries = 3;

    /**
     * @param  list<int>  $variantIds
     */
    public function __construct(
        public readonly array $variantIds,
        public readonly PriceChangeReason $reason,
        public readonly ?int $adminId = null,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(VariantPricingInputs $inputs, SupplierPrices $supplierPrices, CalculateVariantPrice $calculate, ApplyVariantPrices $apply): void
    {
        foreach (array_chunk(array_values(array_unique($this->variantIds)), self::CHUNK) as $variantIds) {
            $this->recalculate($variantIds, $inputs, $supplierPrices, $calculate, $apply);
        }
    }

    /**
     * @param  list<int>  $variantIds
     */
    private function recalculate(array $variantIds, VariantPricingInputs $inputs, SupplierPrices $supplierPrices, CalculateVariantPrice $calculate, ApplyVariantPrices $apply): void
    {
        $pricingInputs = $inputs->for($variantIds);
        $active = $supplierPrices->activeFor($variantIds);
        $latest = VariantPrice::query()
            ->whereKey(array_filter(array_map(fn (VariantPricingInput $input): ?int => $input->currentPriceId, $pricingInputs)))
            ->get()
            ->keyBy(VariantPrice::COLUMN_ID);

        $updates = [];

        DB::transaction(function () use ($pricingInputs, $active, $latest, $calculate, &$updates): void {
            foreach ($pricingInputs as $variantId => $input) {
                $previous = $input->currentPriceId === null ? null : $latest->get($input->currentPriceId);
                $previous = $previous instanceof VariantPrice ? $previous : null;
                $supplier = $active[$variantId] ?? null;
                $calculated = $calculate($input, $supplier instanceof ProviderPrice ? $supplier : null, $previous);

                $stock = CalculateVariantPrice::isSellable($calculated) ? StockStatus::Available : StockStatus::OutOfStock;
                $stockChanges = $stock !== $input->stockStatus;

                if (! CalculateVariantPrice::differs($calculated, $previous)) {
                    if ($stockChanges && $previous instanceof VariantPrice) {
                        $updates[] = new VariantPriceUpdate($variantId, $previous->getPrice(), $previous->getSalePrice(), $previous->getId(), $stock);
                    }

                    continue;
                }

                $record = (new VariantPrice)
                    ->setVariantId($variantId)
                    ->setOfficialPrice($calculated->officialPrice)
                    ->setProviderId($calculated->providerId)
                    ->setProviderCost($calculated->providerCost)
                    ->setCommissionPercent($calculated->commissionPercent)
                    ->setCommissionSource($calculated->commissionSource)
                    ->setPrice($calculated->price)
                    ->setSalePrice($calculated->salePrice)
                    ->setReason($previous instanceof VariantPrice ? $this->reason : PriceChangeReason::Initial)
                    ->setFlags($calculated->flags)
                    ->setChangedBy($this->adminId);
                $record->save();

                $updates[] = new VariantPriceUpdate($variantId, $calculated->price, $calculated->salePrice, $record->getId(), $stock);
            }
        });

        $apply($updates);
    }
}
