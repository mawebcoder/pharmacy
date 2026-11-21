<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * SMS templates (roadmap D11.6, S37): each is a pattern (Melli Payamak) or template (Kavenegar) approved in the
 * provider's panel; the message's variables fill it in order (Kavenegar: %token%, %token2%, %token3%).
 */
enum SmsTemplate: string implements HasLabel
{
    use LabelledEnum;

    case Otp = 'otp';
    // The order was paid: [customer name, order number] (owner, 2026-11-19).
    case OrderPaid = 'order_paid';
    // Handed to Tipax: [customer name, order number, tracking code]; the latest status is in the account (owner, 2026-11-19).
    case OrderOnTheWay = 'order_on_the_way';
    // Cancelled by the customer or an admin: [customer name, order number] (owner, 2026-11-19).
    case OrderCancelled = 'order_cancelled';
    // Its return registered with Tipax: [customer name, order number, return tracking code] (owner, 2026-11-19).
    case OrderReturn = 'order_return';
    // To staff: a customer asked for a return from their account: [customer name, order number, return number] (owner, 2026-11-19).
    case ReturnRequested = 'return_requested';
    // An order staff placed in the panel, or its link sent again: [customer name, order number, payment link] (owner, 2026-11-19).
    case OrderPaymentLink = 'order_payment_link';
    // A refund approved: [customer name, order number, amount in toman] (owner, 2026-11-19).
    case RefundPaid = 'refund_paid';

    public function label(): string
    {
        return match ($this) {
            self::Otp => 'کد ورود',
            self::OrderPaid => 'پرداخت سفارش',
            self::OrderOnTheWay => 'ارسال سفارش',
            self::OrderCancelled => 'لغو سفارش',
            self::OrderReturn => 'مرجوعی سفارش',
            self::ReturnRequested => 'درخواست مرجوعی (به کارکنان)',
            self::OrderPaymentLink => 'لینک پرداخت سفارش',
            self::RefundPaid => 'بازگشت وجه',
        };
    }

    /**
     * Whether its variables are secret (never written to logs).
     */
    public function isSecret(): bool
    {
        return match ($this) {
            self::Otp => true,
            self::OrderPaid, self::OrderOnTheWay, self::OrderCancelled, self::OrderReturn, self::ReturnRequested, self::OrderPaymentLink, self::RefundPaid => false,
        };
    }

    /**
     * How long a queued SMS may still be tried: a sign-in code is useless after a minute, order news is not.
     */
    public function expiresAfterSeconds(): int
    {
        return match ($this) {
            self::Otp => 60,
            self::OrderPaid, self::OrderOnTheWay, self::OrderCancelled, self::OrderReturn, self::ReturnRequested, self::OrderPaymentLink, self::RefundPaid => 3600,
        };
    }
}
