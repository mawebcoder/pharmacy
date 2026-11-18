<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Who pays Tipax for a package and how (`paymentType`, roadmap D6.6). We send SenderPrepaid (S34).
 */
enum TipaxPaymentType: int implements HasLabel
{
    use LabelledEnum;

    case SenderPrepaid = 10;
    case ReceiverCashOnDelivery = 20;
    case ReceiverAtDeliveryPoint = 30;
    case SenderFromCredit = 40;
    case SenderFromWallet = 50;
    case SenderAtOriginFromWallet = 60;
    case SenderCashAtOrigin = 80;
    case SenderFromBranchCredit = 90;

    public function label(): string
    {
        return match ($this) {
            self::SenderPrepaid => 'فرستنده، پیش‌پرداخت در مبدأ',
            self::ReceiverCashOnDelivery => 'گیرنده، پس‌کرایه',
            self::ReceiverAtDeliveryPoint => 'گیرنده، پرداخت در محل تحویل',
            self::SenderFromCredit => 'فرستنده، از اعتبار',
            self::SenderFromWallet => 'فرستنده، از کیف پول',
            self::SenderAtOriginFromWallet => 'فرستنده، در مبدأ از کیف پول',
            self::SenderCashAtOrigin => 'فرستنده، نقدی در مبدأ',
            self::SenderFromBranchCredit => 'فرستنده، از اعتبار نمایندگی',
        };
    }
}
