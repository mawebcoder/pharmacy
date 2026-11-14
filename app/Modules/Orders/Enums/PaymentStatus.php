<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where an order's money stands (roadmap D3.4): waiting at the gateway, paid, never paid (expired), or owed back
 * after a cancellation (refunded by hand until Zarinpal, S26).
 */
enum PaymentStatus: string implements HasLabel
{
    use LabelledEnum;

    case Pending = 'pending';
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار پرداخت',
            self::Paid => 'پرداخت‌شده',
            self::Unpaid => 'پرداخت نشد',
            self::RefundPending => 'در انتظار بازگشت وجه',
            self::Refunded => 'وجه بازگشت داده شد',
        };
    }
}
