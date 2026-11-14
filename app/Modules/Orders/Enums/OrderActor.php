<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Who changed an order's status (roadmap D3.4 order_status_logs).
 */
enum OrderActor: string implements HasLabel
{
    use LabelledEnum;

    case System = 'system';
    case Admin = 'admin';
    case Customer = 'customer';
    case Carrier = 'carrier';

    public function label(): string
    {
        return match ($this) {
            self::System => 'سیستم',
            self::Admin => 'مدیر',
            self::Customer => 'مشتری',
            self::Carrier => 'شرکت حمل',
        };
    }
}
