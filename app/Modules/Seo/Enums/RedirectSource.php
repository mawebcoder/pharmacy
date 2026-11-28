<?php

declare(strict_types=1);

namespace App\Modules\Seo\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Who added a redirect: an admin in «ریدایرکت‌ها», or a slug change (RecordSlugRedirects).
 */
enum RedirectSource: string implements HasLabel
{
    use LabelledEnum;

    case Admin = 'admin';
    case Slug = 'slug';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'دستی',
            self::Slug => 'تغییر نامک',
        };
    }
}
