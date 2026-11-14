<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for a supplier's price of a SKU. Cost in Rial.
 */
final readonly class ProviderPriceData
{
    public function __construct(
        public int $providerId,
        public int $variantId,
        public int $cost,
        public bool $isAvailable,
        public bool $isEnabled,
        public ?int $leadTimeHours,
        public ?float $commissionPercent = null,
        public bool $isPreferred = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the provider_prices columns (ProviderPrice::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $lead = $data[ProviderPrice::COLUMN_LEAD_TIME_HOURS] ?? null;

        return new self(
            providerId: (int) $data[ProviderPrice::COLUMN_PROVIDER_ID],
            variantId: (int) $data[ProviderPrice::COLUMN_VARIANT_ID],
            cost: (int) PersianNormalizer::digits((string) ($data[ProviderPrice::COLUMN_COST] ?? 0)),
            isAvailable: (bool) ($data[ProviderPrice::COLUMN_IS_AVAILABLE] ?? true),
            isEnabled: (bool) ($data[ProviderPrice::COLUMN_IS_ENABLED] ?? true),
            leadTimeHours: blank($lead) ? null : (int) PersianNormalizer::digits((string) $lead),
            commissionPercent: blank($commission = $data[ProviderPrice::COLUMN_COMMISSION_PERCENT] ?? null) ? null : (float) PersianNormalizer::digits((string) $commission),
            isPreferred: (bool) ($data[ProviderPrice::COLUMN_IS_PREFERRED] ?? false),
        );
    }
}
