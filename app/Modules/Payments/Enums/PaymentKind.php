<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What a payment row is for (owner, 2026-11-19): the order's own payment, the difference after staff edit a paid order,
 * or a refund owed to the customer.
 */
enum PaymentKind: string implements HasLabel
{
    use LabelledEnum;

    case Order = 'order';
    case Difference = 'difference';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'پرداخت سفارش',
            self::Difference => 'پرداخت تفاوت',
            self::Refund => 'بازگشت وجه',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Order => 'gray',
            self::Difference => 'info',
            self::Refund => 'warning',
        };
    }
}
