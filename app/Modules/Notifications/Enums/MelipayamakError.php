<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Melli Payamak return values other than a message id (roadmap D11.6): what each means and whether a later retry may
 * work.
 */
enum MelipayamakError: string implements HasLabel
{
    use LabelledEnum;

    case InvalidIp = '-111';
    case ApiKeyRequired = '-110';
    case AllowedIpMissing = '-109';
    case IpBlocked = '-108';
    case LinkInVariables = '-10';
    case LineError = '-7';
    case InternalError = '-6';
    case PatternMismatch = '-5';
    case PatternInvalid = '-4';
    case LineMissing = '-3';
    case OneRecipientOnly = '-2';
    case ServiceDisabled = '-1';
    case WrongCredentials = '0';
    case NoCredit = '2';
    case Updating = '6';
    case FilteredWord = '7';
    case UserInactive = '10';
    case NotSent = '11';
    case DocumentsIncomplete = '12';
    case RecipientNotFound = '16';
    case EmptyText = '17';
    case InvalidRecipient = '18';
    case HourlyLimit = '19';
    case Blacklisted = '35';

    public function label(): string
    {
        return match ($this) {
            self::InvalidIp => 'آی‌پی سرور مجاز نیست',
            self::ApiKeyRequired => 'به جای رمز عبور باید کلید API استفاده شود',
            self::AllowedIpMissing => 'آی‌پی مجاز برای وب‌سرویس تعریف نشده است',
            self::IpBlocked => 'آی‌پی به‌دلیل تلاش‌های ناموفق مسدود شده است',
            self::LinkInVariables => 'متغیرها نباید لینک داشته باشند',
            self::LineError, self::InternalError => 'خطای خط ارسال یا سامانه پیامک',
            self::PatternMismatch => 'متن با متغیرهای الگو جور نیست',
            self::PatternInvalid => 'الگو (bodyId) نادرست است یا تأیید نشده',
            self::LineMissing => 'خط ارسال تعریف نشده است',
            self::OneRecipientOnly => 'در هر درخواست فقط یک گیرنده مجاز است',
            self::ServiceDisabled => 'دسترسی وب‌سرویس غیرفعال است',
            self::WrongCredentials => 'نام کاربری یا کلید API نادرست است',
            self::NoCredit => 'اعتبار پنل پیامک کافی نیست',
            self::Updating => 'سامانه پیامک در حال به‌روزرسانی است',
            self::FilteredWord => 'متن شامل کلمه فیلترشده است',
            self::UserInactive => 'کاربر پنل پیامک فعال نیست',
            self::NotSent => 'پیامک ارسال نشد',
            self::DocumentsIncomplete => 'مدارک پنل پیامک کامل نیست',
            self::RecipientNotFound, self::InvalidRecipient => 'شماره گیرنده نامعتبر است',
            self::EmptyText => 'متن خالی است',
            self::HourlyLimit => 'سقف ارسال ساعتی پر شده است',
            self::Blacklisted => 'شماره گیرنده در لیست سیاه اپراتور است',
        };
    }

    public function isRetryable(): bool
    {
        return in_array($this, [self::InternalError, self::LineError, self::Updating, self::NotSent, self::HourlyLimit], true);
    }
}
