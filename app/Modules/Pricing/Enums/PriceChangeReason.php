<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Why a SKU price record was written (roadmap D3.2, S15).
 */
enum PriceChangeReason: string implements HasLabel
{
    use LabelledEnum;

    case Initial = 'initial';
    case PrintedChanged = 'printed_changed';
    case HarajChanged = 'haraj_changed';
    case CommissionChanged = 'commission_changed';
    case ProviderPriceChanged = 'provider_price_changed';
    case Import = 'import';
    case Recalculated = 'recalculated';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'قیمت اولیه',
            self::PrintedChanged => 'تغییر قیمت روی جعبه',
            self::HarajChanged => 'تغییر حراج',
            self::CommissionChanged => 'تغییر کمیسیون',
            self::ProviderPriceChanged => 'تغییر قیمت یا وضعیت تأمین‌کننده',
            self::Import => 'ورود قیمت از اکسل',
            self::Recalculated => 'محاسبه دوباره',
        };
    }
}
