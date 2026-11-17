<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a menu is shown (roadmap D3.6, S34; owner 2026-11-17). «دسته‌بندی کالاها» and its mega-menu are not a menu:
 * they stay built from the categories.
 */
enum MenuLocation: string implements HasLabel
{
    use LabelledEnum;

    case Header = 'header';
    case Footer1 = 'footer_1';
    case Footer2 = 'footer_2';
    case Footer3 = 'footer_3';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'سربرگ (کنار دسته‌بندی کالاها)',
            self::Footer1 => 'پانویس، ستون ۱',
            self::Footer2 => 'پانویس، ستون ۲',
            self::Footer3 => 'پانویس، ستون ۳',
            self::Mobile => 'منوی موبایل و تبلت',
        };
    }

    /**
     * Where it shows, for the admin.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Header => 'در دسکتاپ، بعد از دکمه «دسته‌بندی کالاها».',
            self::Footer1, self::Footer2, self::Footer3 => 'یک ستون پیوند در پایین همه صفحه‌ها، زیر عنوان منو.',
            self::Mobile => 'در موبایل و تبلت، با دکمه «منو» در سربرگ باز می‌شود.',
        };
    }

    public function isFooter(): bool
    {
        return in_array($this, [self::Footer1, self::Footer2, self::Footer3], true);
    }
}
