<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * The storefront colour theme a customer picks on «اطلاعات حساب» (owner, 2026-11-14). System follows the device;
 * guests always follow the device.
 */
enum ThemePreference: string implements HasLabel
{
    use LabelledEnum;

    case System = 'system';

    case Light = 'light';

    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::System => 'مطابق دستگاه',
            self::Light => 'روشن',
            self::Dark => 'تیره',
        };
    }
}
