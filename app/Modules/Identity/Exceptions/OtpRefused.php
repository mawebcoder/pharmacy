<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Rules\IranianMobile;
use App\Modules\Shared\Support\PersianDigits;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sign-in code that cannot be sent or accepted (roadmap D13.1); the message is shown to the customer as is.
 */
final class OtpRefused extends BaseException
{
    /** Seconds the customer's current code stays valid, when the refusal is because one is still live. */
    public ?int $liveCodeSeconds = null;

    public static function invalidMobile(): self
    {
        return new self(IranianMobile::MESSAGE, 'invalid_mobile');
    }

    public static function liveCodeExists(int $seconds): self
    {
        $seconds = max(1, $seconds);
        $refused = new self(
            'کد تأیید قبلاً برای این شماره ارسال شده و تا '.PersianDigits::toPersian($seconds).' ثانیه دیگر معتبر است؛ همان کد را وارد کنید.',
            'otp_live_code_exists',
            Response::HTTP_CONFLICT,
        );
        $refused->liveCodeSeconds = $seconds;

        return $refused;
    }

    public static function tooManyRequests(): self
    {
        return new self('درخواست‌های زیادی ثبت شده است؛ بعداً دوباره تلاش کنید.', 'otp_too_many_requests', Response::HTTP_TOO_MANY_REQUESTS);
    }

    public static function expired(): self
    {
        return new self('کد منقضی شده است؛ کد جدید بگیرید.', 'otp_expired');
    }

    public static function wrongCode(): self
    {
        return new self('کد واردشده درست نیست.', 'otp_wrong_code');
    }

    public static function tooManyAttempts(): self
    {
        return new self('تعداد تلاش‌ها بیش از حد مجاز شد؛ کد جدید بگیرید.', 'otp_too_many_attempts', Response::HTTP_TOO_MANY_REQUESTS);
    }

    public static function blocked(): self
    {
        return new self('حساب شما مسدود است؛ با پشتیبانی تماس بگیرید.', 'customer_blocked', Response::HTTP_FORBIDDEN);
    }
}
