<?php

declare(strict_types=1);

namespace App\Modules\Cart\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where one use of a coupon stands (roadmap D3.4, S20): applied at checkout (a log only), reserved when the payment
 * link is created, used once the payment is verified, released when the payment fails or expires. Only reserved
 * (while not expired) and used count toward the coupon's limits.
 */
enum CouponUsageStatus: string implements HasLabel
{
    use LabelledEnum;

    case Checkout = 'checkout';
    case Reserved = 'reserved';
    case Used = 'used';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Checkout => 'اعمال در تسویه',
            self::Reserved => 'رزرو تا پرداخت',
            self::Used => 'استفاده‌شده',
            self::Released => 'آزادشده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Checkout => 'gray',
            self::Reserved => 'warning',
            self::Used => 'success',
            self::Released => 'danger',
        };
    }
}
