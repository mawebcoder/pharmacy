<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What a parcel holds for Tipax (Tapin's product_type_id; roadmap D6.0): general, or liquid-fragile when any item in
 * it is liquid or fragile.
 */
enum ParcelContent: string implements HasLabel
{
    use LabelledEnum;

    case General = 'general';
    case LiquidFragile = 'liquid_fragile';

    public function label(): string
    {
        return match ($this) {
            self::General => 'عمومی',
            self::LiquidFragile => 'مایعات-شکستنی',
        };
    }

    /**
     * Tapin's product_type_id: 1 «بسته/عمومی», 4 «مایعات-شکستنی/بسته».
     */
    public function tapinProductType(): int
    {
        return match ($this) {
            self::General => 1,
            self::LiquidFragile => 4,
        };
    }
}
