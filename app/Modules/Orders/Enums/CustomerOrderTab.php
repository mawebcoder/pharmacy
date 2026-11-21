<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * The tabs of «سفارش‌ها» in the account (owner, 2026-11-19): every order, then by where it stands, and the orders with
 * returns (full or partial).
 */
enum CustomerOrderTab: string implements HasLabel
{
    use LabelledEnum;

    case All = 'all';
    case Unpaid = 'unpaid';
    case Processing = 'processing';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::All => 'همه',
            self::Unpaid => 'در انتظار پرداخت',
            self::Processing => 'در حال آماده‌سازی',
            self::OnTheWay => 'ارسال شده',
            self::Delivered => 'تحویل شده',
            self::Cancelled => 'لغو شده',
            self::Returned => 'مرجوعی',
        };
    }

    /**
     * The order statuses in the tab; null for every order (all) or for the returns tab.
     *
     * @return list<OrderStatus>|null
     */
    public function statuses(): ?array
    {
        return match ($this) {
            self::All, self::Returned => null,
            self::Unpaid => [OrderStatus::Pending],
            self::Processing => [OrderStatus::Paid, OrderStatus::Processing],
            self::OnTheWay => [OrderStatus::OnTheWay],
            self::Delivered => [OrderStatus::Delivered],
            self::Cancelled => [OrderStatus::Cancelled, OrderStatus::Expired],
        };
    }
}
