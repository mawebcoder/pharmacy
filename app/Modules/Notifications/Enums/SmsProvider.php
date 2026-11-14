<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * SMS providers (roadmap D11.6). Each case has a sender in SmsManager and a block under «sms.providers»; adding a
 * provider means adding a case, its sender class and its config.
 */
enum SmsProvider: string implements HasLabel
{
    use LabelledEnum;

    case Melipayamak = 'melipayamak';

    public function label(): string
    {
        return match ($this) {
            self::Melipayamak => 'ملی پیامک',
        };
    }

    /**
     * The provider chosen in config/sms.php (SMS_PROVIDER).
     */
    public static function configured(): self
    {
        return self::from((string) config('sms.provider'));
    }
}
