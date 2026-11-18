<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Zarinpal;

/**
 * Zarinpal's result codes (roadmap D7.3) and what the customer or admin is told. 100 and 101 are success.
 */
enum ZarinpalError: int
{
    case Validation = -9;
    case InvalidMerchant = -10;
    case TerminalInactive = -11;
    case TooManyAttempts = -12;
    case TerminalLimit = -13;
    case CallbackDomainMismatch = -14;
    case TerminalSuspended = -15;
    case TerminalLevelTooLow = -16;
    case TerminalRestricted = -17;
    case TransactionsBanned = -19;
    case AmountTooHigh = -41;
    case AmountMismatch = -50;
    case PaymentFailed = -51;
    case OtherMerchant = -53;
    case InvalidAuthority = -54;

    /**
     * A Persian message for a code, or a general one for codes not listed here.
     */
    public static function messageFor(?int $code): string
    {
        return self::tryFrom((int) $code)?->message() ?? 'پرداخت در درگاه زرین‌پال انجام نشد (کد '.($code ?? 'نامشخص').').';
    }

    public function message(): string
    {
        return match ($this) {
            self::Validation => 'اطلاعات درخواست پرداخت معتبر نیست.',
            self::InvalidMerchant => 'کد پذیرنده یا آی‌پی سرور در زرین‌پال معتبر نیست.',
            self::TerminalInactive => 'درگاه پرداخت فعال نیست.',
            self::TooManyAttempts => 'تلاش‌های زیادی در زمان کوتاه انجام شده است؛ کمی بعد دوباره تلاش کنید.',
            self::TerminalLimit => 'سقف تراکنش‌های درگاه پر شده است.',
            self::CallbackDomainMismatch => 'آدرس بازگشت با دامنه ثبت‌شده در زرین‌پال یکی نیست.',
            self::TerminalSuspended, self::TerminalLevelTooLow, self::TerminalRestricted, self::TransactionsBanned => 'درگاه پرداخت فعلاً امکان تراکنش ندارد.',
            self::AmountTooHigh => 'مبلغ بیش از سقف مجاز درگاه است.',
            self::AmountMismatch => 'مبلغ پرداخت با مبلغ سفارش یکی نیست.',
            self::PaymentFailed => 'پرداخت ناموفق بود.',
            self::OtherMerchant, self::InvalidAuthority => 'شناسه پرداخت معتبر نیست.',
        };
    }
}
