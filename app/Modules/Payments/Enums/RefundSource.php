<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a refund record came from (owner, 2026-11-19): staff lowered a paid order's total, a return was received, or a
 * paid order was cancelled.
 */
enum RefundSource: string implements HasLabel
{
    use LabelledEnum;

    case OrderEdit = 'order_edit';
    case OrderReturn = 'order_return';
    case OrderCancel = 'order_cancel';

    public function label(): string
    {
        return match ($this) {
            self::OrderEdit => 'ویرایش سفارش',
            self::OrderReturn => 'مرجوعی',
            self::OrderCancel => 'لغو سفارش',
        };
    }
}
