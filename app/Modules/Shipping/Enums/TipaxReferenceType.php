<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * The Tipax lists copied by `tipax:sync-reference` (roadmap S33).
 */
enum TipaxReferenceType: string implements HasLabel
{
    use LabelledEnum;

    case Service = 'service';
    case PackType = 'pack_type';
    case Packing = 'packing';
    case Content = 'content';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'سرویس',
            self::PackType => 'نوع بسته',
            self::Packing => 'بسته‌بندی',
            self::Content => 'محتوای بسته',
        };
    }
}
