<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\FlashDeal;
use Illuminate\Support\Carbon;

/**
 * A flash deal as the admin typed it (roadmap S33): SKU, the supplier it sells from (always chosen; null only before
 * validation), deal price (rial), window, optional unit limit, on or off.
 */
final readonly class FlashDealData
{
    public function __construct(
        public int $variantId,
        public int $dealPrice,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public ?int $quantityLimit,
        public bool $isActive,
        public ?int $providerId = null,
    ) {}

    /**
     * Keys are the flash_deals columns (FlashDeal::COLUMN_*).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $limit = $data[FlashDeal::COLUMN_QUANTITY_LIMIT] ?? null;

        return new self(
            variantId: (int) ($data[FlashDeal::COLUMN_VARIANT_ID] ?? 0),
            dealPrice: (int) ($data[FlashDeal::COLUMN_DEAL_PRICE] ?? 0),
            startsAt: Carbon::parse((string) ($data[FlashDeal::COLUMN_STARTS_AT] ?? 'now')),
            endsAt: Carbon::parse((string) ($data[FlashDeal::COLUMN_ENDS_AT] ?? 'now')),
            quantityLimit: blank($limit) ? null : (int) $limit,
            isActive: (bool) ($data[FlashDeal::COLUMN_IS_ACTIVE] ?? true),
            providerId: blank($data[FlashDeal::COLUMN_PROVIDER_ID] ?? null) ? null : (int) $data[FlashDeal::COLUMN_PROVIDER_ID],
        );
    }
}
