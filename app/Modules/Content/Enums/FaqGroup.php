<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * The headings of /faq (owner, 2026-11-17: a fixed list), in the order the page shows them.
 */
enum FaqGroup: string implements HasLabel
{
    use LabelledEnum;

    case OrderAndShipping = 'order_shipping';
    case Payment = 'payment';
    case Returns = 'returns';
    case Account = 'account';
    case Medicine = 'medicine';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OrderAndShipping => 'سفارش و ارسال',
            self::Payment => 'پرداخت',
            self::Returns => 'بازگشت کالا',
            self::Account => 'حساب کاربری',
            self::Medicine => 'دارو و نسخه',
            self::Other => 'سایر',
        };
    }
}
