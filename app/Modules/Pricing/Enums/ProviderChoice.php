<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How the supplier the website sells a SKU from was chosen (roadmap D3.2, S15b).
 */
enum ProviderChoice: string implements HasLabel
{
    use LabelledEnum;

    case PreferredSku = 'preferred_sku';
    case PreferredProduct = 'preferred_product';
    case Cheapest = 'cheapest';
    // A live flash deal's supplier replaces the default one (S33, owner 2026-11-17); set on order items, not price records.
    case FlashDeal = 'flash_deal';

    public function label(): string
    {
        return match ($this) {
            self::PreferredSku => 'انتخابی (SKU)',
            self::PreferredProduct => 'انتخابی (محصول)',
            self::Cheapest => 'خودکار: کمترین قیمت فروش',
            self::FlashDeal => 'پیشنهاد لحظه‌ای',
        };
    }
}
