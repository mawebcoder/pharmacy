<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Product lifecycle (roadmap D3.1). Only active products are shown and sold on the storefront.
 */
enum ProductStatus: string implements HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Active = 'active';
    case Hidden = 'hidden';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Active => 'فعال',
            self::Hidden => 'پنهان',
            self::Discontinued => 'توقف فروش',
        };
    }

    /**
     * Filament badge colour (same meaning as on the storefront, roadmap B12).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Hidden => 'warning',
            self::Discontinued => 'danger',
        };
    }
}
