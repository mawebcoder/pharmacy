<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Order statuses (roadmap D3.5, owner 2026-11-14). The system moves pending orders to paid or expired; the admin
 * moves paid → processing → on the way → delivered (Tipax webhooks may set the last two from S27); the customer may
 * cancel a paid order, the admin a paid or processing one.
 */
enum OrderStatus: string implements HasLabel
{
    use LabelledEnum;

    case Pending = 'pending';
    case Expired = 'expired';
    case Paid = 'paid';
    case Processing = 'processing';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار پرداخت',
            self::Expired => 'منقضی‌شده',
            self::Paid => 'پرداخت‌شده',
            self::Processing => 'در حال آماده‌سازی',
            self::OnTheWay => 'در راه',
            self::Delivered => 'تحویل شد',
            self::Cancelled => 'لغو شد',
        };
    }

    /**
     * Badge colour (TallStackUI / Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Expired, self::Cancelled => 'gray',
            self::Paid, self::Processing => 'primary',
            self::OnTheWay => 'info',
            self::Delivered => 'success',
        };
    }

    /**
     * The statuses this one may move to, by who moves it.
     *
     * @return list<self>
     */
    public function nextFor(OrderActor $actor): array
    {
        return match ([$this, $actor]) {
            [self::Pending, OrderActor::System] => [self::Paid, self::Expired],
            // A payment verified just after the window closed is still honoured: the money was taken.
            [self::Expired, OrderActor::System] => [self::Paid],
            [self::Paid, OrderActor::Admin] => [self::Processing, self::Cancelled],
            [self::Paid, OrderActor::Customer] => [self::Cancelled],
            [self::Processing, OrderActor::Admin] => [self::OnTheWay, self::Cancelled],
            [self::OnTheWay, OrderActor::Admin], [self::OnTheWay, OrderActor::Carrier] => [self::Delivered],
            [self::Processing, OrderActor::Carrier] => [self::OnTheWay],
            default => [],
        };
    }

    public function canMoveTo(self $to, OrderActor $actor): bool
    {
        return in_array($to, $this->nextFor($actor), true);
    }

    /**
     * Counts as a placed order for «first order only» coupons: paid at some point and not cancelled.
     */
    public function isPurchase(): bool
    {
        return in_array($this, [self::Paid, self::Processing, self::OnTheWay, self::Delivered], true);
    }
}
