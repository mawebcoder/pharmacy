<?php

declare(strict_types=1);

namespace App\Modules\Cart\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What a coupon gives (roadmap D3.4): a percentage (with an optional cap), a fixed amount (rial), or free shipping.
 */
enum CouponType: string implements HasLabel
{
    use LabelledEnum;

    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
            self::FreeShipping => 'ارسال رایگان',
        };
    }
}
