<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Kavenegar's error statuses (its API's «return.status» other than 200), with what admins see in «پیامک‌ها» and
 * whether trying again later may work.
 */
enum KavenegarError: int implements HasLabel
{
    use LabelledEnum;

    case BadRequest = 400;
    case AccountInactive = 401;
    case OperationFailed = 402;
    case InvalidApiKey = 403;
    case UnknownMethod = 404;
    case WrongHttpMethod = 405;
    case MissingParameters = 406;
    case AccessDenied = 407;
    case Busy = 409;
    case InvalidRecipient = 411;
    case InvalidSender = 412;
    case MessageTooLong = 413;
    case TooManyRecipients = 414;
    case InvalidIndex = 415;
    case IpNotAllowed = 417;
    case NoCredit = 418;
    case ArrayLengthMismatch = 419;
    case LinkNotAllowed = 420;
    case TemplateNotFound = 424;
    case AdvancedServiceOnly = 426;
    case AccountNotVerified = 430;
    case BlockedRecipient = 432;

    public function label(): string
    {
        return match ($this) {
            self::BadRequest, self::MissingParameters, self::InvalidIndex, self::ArrayLengthMismatch => 'پارامترهای ارسال کامل یا درست نیست',
            self::AccountInactive => 'حساب کاوه‌نگار غیرفعال است',
            self::OperationFailed => 'ارسال پیامک ناموفق بود',
            self::InvalidApiKey => 'کلید API کاوه‌نگار معتبر نیست',
            self::UnknownMethod, self::WrongHttpMethod => 'درخواست به کاوه‌نگار نادرست است',
            self::AccessDenied => 'دسترسی به این سرویس کاوه‌نگار مجاز نیست',
            self::Busy => 'سرور کاوه‌نگار مشغول است',
            self::InvalidRecipient => 'شماره گیرنده معتبر نیست',
            self::InvalidSender => 'خط ارسال معتبر نیست',
            self::MessageTooLong => 'متن پیامک طولانی است',
            self::TooManyRecipients => 'تعداد گیرندگان بیش از حد مجاز است',
            self::IpNotAllowed => 'آی‌پی سرور در کاوه‌نگار مجاز نیست',
            self::NoCredit => 'اعتبار حساب کاوه‌نگار کافی نیست',
            self::LinkNotAllowed => 'متغیرهای الگو نباید لینک داشته باشند',
            self::TemplateNotFound => 'الگوی پیامک در کاوه‌نگار پیدا نشد یا تأیید نشده است',
            self::AdvancedServiceOnly => 'این سرویس نیاز به حساب پیشرفته کاوه‌نگار دارد',
            self::AccountNotVerified => 'حساب کاوه‌نگار احراز هویت نشده است',
            self::BlockedRecipient => 'گیرنده دریافت پیامک را مسدود کرده است',
        };
    }

    public function isRetryable(): bool
    {
        return in_array($this, [self::Busy, self::OperationFailed], true);
    }
}
