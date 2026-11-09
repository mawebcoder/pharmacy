<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Which Catalog input of a SKU's price changed (roadmap D3.2): Pricing recalculates the price on each.
 */
enum PricingInputChange: string implements HasLabel
{
    use LabelledEnum;

    case Created = 'created';
    case Printed = 'printed';
    case Haraj = 'haraj';
    case Commission = 'commission';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'تنوع جدید',
            self::Printed => 'تغییر قیمت روی جعبه',
            self::Haraj => 'تغییر حراج',
            self::Commission => 'تغییر کمیسیون',
        };
    }
}
