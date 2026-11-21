<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\Toman;

/**
 * A refund record that cannot be opened or decided (owner, 2026-11-19); shown to staff as is.
 */
final class RefundRefused extends BaseException
{
    public static function empty(): self
    {
        return new self('مبلغ بازگشت وجه باید بیشتر از صفر باشد.', 'refund_empty');
    }

    public static function tooLarge(int $refundable): self
    {
        return new self('مبلغ بازگشت وجه از مبلغ قابل بازگشت این سفارش ('.Toman::format($refundable).') بیشتر است.', 'refund_too_large');
    }

    public static function alreadyOpened(): self
    {
        return new self('برای این مرجوعی قبلاً بازگشت وجه ثبت شده است.', 'refund_already_opened');
    }

    public static function alreadyDecided(): self
    {
        return new self('این بازگشت وجه قبلاً تأیید یا رد شده است.', 'refund_already_decided');
    }
}
