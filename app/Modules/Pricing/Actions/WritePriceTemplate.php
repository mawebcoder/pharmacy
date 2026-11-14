<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Data\VariantSummary;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Support\PriceSpreadsheet;
use App\Modules\Shared\Support\Toman;

/**
 * Writes the supplier price template (roadmap S16): every current supplier price, one row per supplier and SKU, in
 * toman, ready to edit and upload back. Rows of SKUs that no longer exist are left out.
 */
final readonly class WritePriceTemplate
{
    public function __construct(private VariantPricingInputs $variants) {}

    public function __invoke(string $path): void
    {
        PriceSpreadsheet::writeTemplate($path, $this->rows());
    }

    /**
     * @return \Generator<list<string|int|float>>
     */
    private function rows(): \Generator
    {
        $chunks = ProviderPrice::query()->with('provider')->orderBy(ProviderPrice::COLUMN_PROVIDER_ID)->orderBy(ProviderPrice::COLUMN_VARIANT_ID)->lazy(500)->chunk(500);

        foreach ($chunks as $prices) {
            $summaries = $this->variants->summaries(array_values($prices->map(fn (ProviderPrice $price): int => $price->getVariantId())->all()));

            foreach ($prices as $price) {
                $sku = $summaries[$price->getVariantId()] ?? null;

                if (! $sku instanceof VariantSummary) {
                    continue;
                }

                yield [
                    $price->getProviderId(),
                    $price->getProvider()?->getName() ?? '',
                    $sku->sku,
                    $sku->barcode ?? '',
                    $sku->productName.' · '.$sku->title,
                    Toman::fromRial($price->getCost()),
                    $price->isAvailable() ? 'بله' : 'خیر',
                    $price->isEnabled() ? 'بله' : 'خیر',
                    $price->getLeadTimeHours() ?? '',
                    $price->getCommissionPercent() ?? '',
                    $price->isPreferred() ? 'بله' : 'خیر',
                ];
            }
        }
    }
}
