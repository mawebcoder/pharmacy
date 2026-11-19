<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How much of an order comes back (roadmap S27, owner 2026-11-19): every unit of every item, or only some.
 */
enum ReturnScope: string implements HasLabel
{
    use LabelledEnum;

    case Full = 'full';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'مرجوعی کامل',
            self::Partial => 'مرجوعی جزئی',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Full => 'danger',
            self::Partial => 'warning',
        };
    }
}
