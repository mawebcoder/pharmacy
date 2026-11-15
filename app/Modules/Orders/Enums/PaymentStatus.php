<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where an order's money stands (roadmap D3.4): waiting at the gateway, paid, expired unpaid («منقضی‌شده», like the
 * order; owner, 2026-11-15, was «unpaid»), or owed back after a cancellation (refunded by hand until Zarinpal, S26).
 */
enum PaymentStatus: string implements HasLabel
{
    use LabelledEnum;

    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار پرداخت',
            self::Paid => 'پرداخت‌شده',
            self::Expired => 'منقضی‌شده',
            self::RefundPending => 'در انتظار بازگشت وجه',
            self::Refunded => 'وجه بازگشت داده شد',
        };
    }

    /**
     * Badge colour (TallStackUI / Filament names); money owed back stands out.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Expired => 'gray',
            self::RefundPending => 'danger',
            self::Refunded => 'info',
        };
    }
}
