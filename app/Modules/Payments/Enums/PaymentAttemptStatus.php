<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * One payment attempt at the gateway (roadmap D7.4): waiting, paid, failed (refused or cancelled at the bank) or
 * expired (its order's payment window passed).
 */
enum PaymentAttemptStatus: string implements HasLabel
{
    use LabelledEnum;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار پرداخت',
            self::Paid => 'پرداخت‌شده',
            self::Failed => 'ناموفق',
            self::Expired => 'منقضی‌شده',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Expired => 'gray',
        };
    }
}
