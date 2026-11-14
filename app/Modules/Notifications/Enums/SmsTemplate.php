<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * SMS templates (Melli Payamak patterns, roadmap D11.6). S28 adds the order templates.
 */
enum SmsTemplate: string implements HasLabel
{
    use LabelledEnum;

    case Otp = 'otp';

    public function label(): string
    {
        return match ($this) {
            self::Otp => 'کد ورود',
        };
    }

    /**
     * Whether its variables are secret (never written to logs).
     */
    public function isSecret(): bool
    {
        return match ($this) {
            self::Otp => true,
        };
    }
}
