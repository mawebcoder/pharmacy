<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What a contact message is about (roadmap S29), picked on /contact.
 */
enum ContactSubject: string implements HasLabel
{
    use LabelledEnum;

    case Order = 'order';
    case Payment = 'payment';
    case Return = 'return';
    case Product = 'product';
    case Suggestion = 'suggestion';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'پیگیری سفارش',
            self::Payment => 'پرداخت',
            self::Return => 'بازگشت کالا',
            self::Product => 'پرسش درباره کالا',
            self::Suggestion => 'پیشنهاد یا انتقاد',
            self::Other => 'سایر',
        };
    }
}
