<?php

declare(strict_types=1);

namespace App\Modules\Settings\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * A social network the store links to from the footer (roadmap D3.6 social links, S30). The footer draws each one's
 * icon (settings::social-icon).
 */
enum SocialPlatform: string implements HasLabel
{
    use LabelledEnum;

    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Eitaa = 'eitaa';
    case Bale = 'bale';
    case Rubika = 'rubika';
    case Aparat = 'aparat';
    case YouTube = 'youtube';
    case LinkedIn = 'linkedin';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'اینستاگرام',
            self::Telegram => 'تلگرام',
            self::WhatsApp => 'واتس‌اپ',
            self::Eitaa => 'ایتا',
            self::Bale => 'بله',
            self::Rubika => 'روبیکا',
            self::Aparat => 'آپارات',
            self::YouTube => 'یوتیوب',
            self::LinkedIn => 'لینکدین',
            self::X => 'ایکس (توییتر)',
        };
    }
}
